<?php

require __DIR__ . '/bootstrap.php';

use Tester\Assert;

$io = new IOInterface;
$cleaner = new DG\ComposerCleaner\Cleaner($io, new Filesystem);

Assert::equal((object) [
	'name' => 'dg/composer-cleaner',
	'type' => 'composer-plugin',
], $cleaner->loadComposerJson(__DIR__ . '/fixtures'));
Assert::same([], $io->getLog());


Assert::null($cleaner->loadComposerJson(__DIR__ . '/not-exists'));
Assert::same([[
	'writeError',
	['File ' . __DIR__ . '/not-exists/composer.json not found.', true, IOInterface::VERBOSE],
]], $io->getLog());
