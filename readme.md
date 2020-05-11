Victor The Cleaner for Composer
===============================

[![Downloads this Month](https://img.shields.io/packagist/dm/dg/composer-cleaner.svg)](https://packagist.org/packages/dg/composer-cleaner)
[![Build Status](https://travis-ci.org/dg/composer-cleaner.svg?branch=master)](https://travis-ci.org/dg/composer-cleaner)

This tool removes unnecessary files and directories from Composer `vendor` directory.

The Cleaner leaves only directories containing the source files needed to use the libraries.
These are located according to the [autoload section](https://getcomposer.org/doc/04-schema.md#autoload) of `composer.json` in each installed library.
Conversely for example, tests are files that are not needed for use, so they are removed.

Installation
------------

```
composer require dg/composer-cleaner
```

Then simply run `composer update` or `composer require ...` and the Cleaner automatically removes unnecessary files when new libraries are installed.


Configuration
-------------

Some libraries also requires other files/directories, which the the Cleaner judged to be unnecessary.
In this case, you can list them (specify paths to be ignored), in the configuration and the tool will keep them.
Or you can specify that some libraries should not be cleaned at all.

Simply add a `extra > cleaner-ignore` section to `composer.json` file:

```js
{
	"extra": {
		"cleaner-ignore": {
			"slevomat/eet-client": [  // name of package
				"wsdl*"               // list of files or subdirectories, you can use wildcards `*` and `?`
			],

			"mpdf/mpdf": true         // ignores whole package
		}
	}
}
```

Support Project
---------------

Do you like Victor The Cleaner? Are you looking forward to the new features?

[![Donate](https://files.nette.org/icons/donation-1.svg?)](https://nette.org/make-donation?to=composer-cleaner)
