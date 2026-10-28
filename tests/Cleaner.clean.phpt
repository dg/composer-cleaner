<?php declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;


function clean(array $ignore): array
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
		@mkdir(dirname("$vendorDir/a/b/$file"), 0o777, true);
		file_put_contents("$vendorDir/a/b/$file", $content);
	}

	$fileSystem = new Filesystem;
	$cleaner = new DG\ComposerCleaner\Cleaner(new IOInterface, $fileSystem);
	$cleaner->clean($vendorDir, ['a/b' => $ignore]);

	$removed = [];
	foreach ($fileSystem->getLog() as [$method, $args]) {
		if ($method === 'remove') {
			$removed[] = strtr(substr($args[0], strlen("$vendorDir/a/b/")), '\\', '/');
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
