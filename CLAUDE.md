# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Victor The Cleaner is a Composer plugin that automatically removes unnecessary files and directories from the `vendor` directory after `composer install` or `composer update`. The plugin analyzes each installed package's `composer.json` to determine which directories contain actual source files (based on the `autoload` section) and removes everything else (like tests, documentation, etc.).

## Core Architecture

### Plugin Activation
- **Entry point:** `Plugin` class implements Composer's `PluginInterface` and `EventSubscriberInterface`
- **Activation:** Automatically runs after `POST_UPDATE_CMD` and `POST_INSTALL_CMD` events
- **Configuration:** Users can specify paths to ignore via `extra.cleaner-ignore` in their project's `composer.json`

### Cleaning Logic
The `Cleaner` class implements the core cleaning algorithm:

1. **Package Discovery:** Iterates through `vendor/vendor-name/package-name` directories
2. **Autoload Analysis:** Parses each package's `composer.json` to extract:
   - `autoload.psr-0` - PSR-0 namespace paths
   - `autoload.psr-4` - PSR-4 namespace paths
   - `autoload.classmap` - Classmap directories/files
   - `autoload.files` - Files to include
   - `bin` - Binary/executable files
3. **Exclusion Handling:** Removes paths listed in `autoload.exclude-from-classmap`
4. **Protection:** Always preserves `composer.json`, `license*`, `LICENSE*`, and `.phpstorm.meta.php`
5. **Pattern Matching:** Uses `fnmatch()` for wildcard pattern support (`*`, `?`)

### Configuration Format
Users can configure ignored paths in their project's `composer.json`:
```json
{
  "extra": {
    "cleaner-ignore": {
      "vendor/package": ["path*", "otherpath"],
      "vendor/whole-package": true
    }
  }
}
```

## Development Commands

### Testing
```bash
# Run all tests
composer run tester

# Run specific test file
vendor/bin/tester tests/Cleaner.loadComposerJson.phpt -s -C

# Run tests in a directory
vendor/bin/tester tests/ -s -C
```

Tests use Nette Tester (`.phpt` files) with custom mocks for Composer interfaces defined in `tests/mocks.php`.

### Static Analysis
```bash
# Run PHPStan analysis
composer run phpstan
```

PHPStan is configured at level 5 and analyzes the `src/` directory.

## Code Conventions

- Use `declare(strict_types=1)` in all PHP files
- Support PHP 7.1+ (minimum version requirement)
- Follow Nette coding standards
- Use Composer plugin API v1 or v2
