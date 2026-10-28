<?php declare(strict_types=1);

// packages needing files outside autoloaded directories at runtime
return [
	'composer/ca-bundle' => ['res'],
	'stripe/stripe-php' => ['data'],
];
