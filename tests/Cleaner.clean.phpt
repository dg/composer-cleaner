<?php

require __DIR__ . '/bootstrap.php';

use Tester\Assert;

$io = new IOInterface;
$cleaner = new DG\ComposerCleaner\Cleaner($io, new Filesystem);
$vendorDir = __DIR__ . '/fixtures/vendor';

$cleaner->clean($vendorDir, [
	'mpdf/mpdf' => [
		'data/mpdf.css',
		'!src/QrCode/data/',
		'ttfonts/DejaVuSans.txt',
	],
]);

$toRemove = [];

foreach ($io->getLog() as $log) {
	if (isset($log[1][0]) && substr($log[1][0], 18, 8) === 'Removing') {
		$toRemove[] = $log[1][0];
	}
}

sort($toRemove, SORT_STRING);

Assert::same([
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/.github',
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/CHANGELOG.md',
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/data/lang2fonts.css',
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/src/QrCode/data',
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/ttfonts/Arial.txt',
	'Composer cleaner: Removing ' . $vendorDir . '/mpdf/mpdf/ttfonts/license.txt',
], $toRemove);
