<?php

if (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
} else {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use DGComposer\Cleaner;


$cmd = new Nette\CommandLine\Parser(<<<XX
Usage:
    php composer-cleaner.php [options] [<path>]

Options:
	-t | --test      Run in test-mode.


XX
, [
	'path' => [Nette\CommandLine\Parser::VALUE => getcwd()],
]);

$options = $cmd->parse();
if ($cmd->isEmpty()) {
	$cmd->help();
}

$cleaner = new Cleaner($options['--test']);
$cleaner->clean($options['path']);
