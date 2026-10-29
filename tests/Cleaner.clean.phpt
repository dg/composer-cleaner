<?php declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;


function clean(array $ignore, string $name = 'a/b'): array
{
	$vendorDir = __DIR__ . '/output/vendor';
	Tester\Helpers::purge($vendorDir);
	$files = [
		'composer.json' => '{"autoload": {"psr-4": {"A\\\\": "src/"}}}',
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
		@mkdir(dirname("$vendorDir/$name/$file"), 0o777, true);
		file_put_contents("$vendorDir/$name/$file", $content);
	}

	$fileSystem = new FilesystemMock;
	$cleaner = new DG\ComposerCleaner\Cleaner(new IOMock, $fileSystem);
	$cleaner->clean($vendorDir, [$name => $ignore]);

	$removed = [];
	foreach ($fileSystem->getLog() as [$method, $args]) {
		if ($method === 'remove') {
			$removed[] = strtr(substr($args[0], strlen("$vendorDir/$name/")), '\\', '/');
		}
	}
	sort($removed);
	return $removed;
}


Assert::same(['data', 'readme.md', 'tests', 'wsdl1'], clean([]));
Assert::same(['data', 'readme.md', 'tests'], clean(['wsdl*']));
Assert::same(['readme.md', 'tests', 'wsdl1'], clean(['data']));
Assert::same(['readme.md', 'tests', 'wsdl1'], clean(['data/']));
Assert::same(['data/other.css', 'data/sub', 'readme.md', 'tests', 'wsdl1'], clean(['data/keep.crt']));
Assert::same(['data/keep.crt', 'data/sub', 'readme.md', 'tests/fixtures', 'wsdl1/a.wsdl'], clean(['*/*.css']));
Assert::same(['data/keep.crt', 'data/other.css', 'readme.md', 'tests', 'wsdl1'], clean(['data/sub/x.css']));
Assert::same(['data', 'readme.md', 'tests', 'wsdl1'], clean(['*.css']));

// built-in ignore list
Assert::same(['readme.md', 'tests', 'wsdl1'], clean([], 'stripe/stripe-php'));
Assert::same(['readme.md', 'tests'], clean(['wsdl*'], 'stripe/stripe-php'));
