Victor The Cleaner for Composer
===============================

[![Downloads this Month](https://img.shields.io/packagist/dm/dg/composer-cleaner.svg)](https://packagist.org/packages/dg/composer-cleaner)
[![Build Status](https://travis-ci.org/dg/composer-cleaner.svg?branch=master)](https://travis-ci.org/dg/composer-cleaner)

This tool removes unnecessary files and directories from Composer vendor directory.

Installation
------------

```
composer require dg/composer-cleaner
```

Then simply use `composer update`.


Configuration
-------------

You can also specify paths to be ignored via `composer.json`:

```js
{
	"config": {
		"cleaner-ignore": {
			"slevomat/eet-client": [  // name of package
				"wsdl"                // one or more subdirectories
			]
		}
	}
}
```
