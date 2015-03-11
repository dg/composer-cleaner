<?php

require __DIR__ . '/../src/Cleaner.php';

set_exception_handler(function($e) {
	echo "ERROR: {$e->getMessage()}\n";
	exit(1);
});

$argv = array_slice($_SERVER['argv'], 1); // indexed from 0

$cleaner = new Cleaner($testMode = isset($argv[0]) && ($argv[0] === '-t' || $argv[0] === '--test'));
$cleaner->clean(isset($argv[0]) && !$testMode ? $argv[0] : (isset($argv[1]) ? $argv[1] : getcwd()));
