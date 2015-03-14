<?php

require __DIR__ . '/../vendor/autoload.php';


set_exception_handler(function($e) {
	echo "ERROR: {$e->getMessage()}\n";
	exit(1);
});


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
