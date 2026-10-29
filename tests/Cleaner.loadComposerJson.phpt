<?php declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Tester\Assert;

$io = new IOMock;
$cleaner = new DG\ComposerCleaner\Cleaner($io, new FilesystemMock);

Assert::equal((object) [
	'name' => 'dg/composer-cleaner',
	'type' => 'composer-plugin',
], $cleaner->loadComposerJson(__DIR__ . '/fixtures'));
Assert::same([], $io->getLog());


Assert::null($cleaner->loadComposerJson(__DIR__ . '/not-exists'));
Assert::same([[
	'writeError',
	['Composer cleaner: File ' . __DIR__ . '/not-exists/composer.json not found.', true, Composer\IO\IOInterface::VERBOSE],
]], $io->getLog());
