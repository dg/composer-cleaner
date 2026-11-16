<?php declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;


function clean(array $ignore, string $name = 'a/b'): array
{
	$vendorDir = __DIR__ . '/output/vendor';
	Tester\Helpers::purge($vendorDir);
	$files = [
		'composer.json' => '{"autoload": {"psr-4": {"A\\\\": "src/"}}}',
		'AGENTS.md' => '',
		'LICENSE' => '',
		'readme.md' => '',
		'src/A.php' => '',
		'data/keep.crt' => '',
		'data/other.css' => '',
		'data/sub/x.css' => '',
		'tests/fixtures/a.css' => '',
		'wsdl1/a.wsdl' => '',
	];
	foreach ($files as $file => $content) {
		@mkdir(dirname("$vendorDir/$name/$file"), 0o777, true); // @ directory may already exist
		file_put_contents("$vendorDir/$name/$file", $content);
	}

	$symlinks = [
		'CLAUDE.md' => 'AGENTS.md',
	];
	foreach ($symlinks as $symlink => $target) {
		@mkdir(dirname("$vendorDir/$name/$symlink"), 0o777, true); // @ directory may already exist
		symlink("$vendorDir/$name/$target", "$vendorDir/$name/$symlink");
	}

	$fileSystem = new FilesystemMock;
	$cleaner = new DG\ComposerCleaner\Cleaner(new IOMock, $fileSystem);
	$cleaner->clean($vendorDir, [$name => $ignore]);

	$removed = [];
	foreach ($fileSystem->getLog() as [$method, $args]) {
		if (($method === 'remove') || ($method === 'unlink')) {
			$removed[] = strtr(substr($args[0], strlen("$vendorDir/$name/")), '\\', '/');
		}
	}
	// Check explicitly that symlink files are actually gone.
	foreach ($symlinks as $symlink => $target) {
	    if (is_link("$vendorDir/$name/$symlink")) {
	        // The symlink has not actually gone. So remove it from the array of removed items.
	        $removed = array_diff($removed, [$symlink]);
	    }
	}
	sort($removed);
	return $removed;
}


Assert::same(['AGENTS.md', 'CLAUDE.md', 'data', 'readme.md', 'tests', 'wsdl1'], clean([]));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'data', 'readme.md', 'tests'], clean(['wsdl*']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'readme.md', 'tests', 'wsdl1'], clean(['data']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'readme.md', 'tests', 'wsdl1'], clean(['data/']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'data/other.css', 'data/sub', 'readme.md', 'tests', 'wsdl1'], clean(['data/keep.crt']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'data/keep.crt', 'data/sub', 'readme.md', 'tests/fixtures', 'wsdl1/a.wsdl'], clean(['*/*.css']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'data/keep.crt', 'data/other.css', 'readme.md', 'tests', 'wsdl1'], clean(['data/sub/x.css']));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'data', 'readme.md', 'tests', 'wsdl1'], clean(['*.css']));

// built-in ignore list
Assert::same(['AGENTS.md', 'CLAUDE.md', 'readme.md', 'tests', 'wsdl1'], clean([], 'stripe/stripe-php'));
Assert::same(['AGENTS.md', 'CLAUDE.md', 'readme.md', 'tests'], clean(['wsdl*'], 'stripe/stripe-php'));
