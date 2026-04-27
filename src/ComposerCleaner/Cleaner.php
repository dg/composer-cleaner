<?php declare(strict_types=1);

/**
 * Victor The Cleaner for Composer.
 *
 * Copyright (c) 2015 David Grudl (https://davidgrudl.com)
 */

namespace DG\ComposerCleaner;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use FilesystemIterator;
use stdClass;


class Cleaner
{
	private int $removedCount = 0;

	/** @var array */
	private static array $allowedComposerTypes = [null, 'library', 'composer-plugin'];

	/** @var string[] */
	private static array $alwaysIgnore = ['composer.json', 'license*', 'LICENSE*', '.phpstorm.meta.php'];


	public function __construct(
		private readonly IOInterface $io,
		private readonly Filesystem $fileSystem,
	) {
	}


	public function clean(string $vendorDir, array $ignorePaths = []): void
	{
		foreach (new FilesystemIterator($vendorDir) as $packageVendor) {
			if (!$packageVendor->isDir()) {
				continue;
			}
			foreach (new FilesystemIterator((string) $packageVendor) as $packageName) {
				if (!$packageName->isDir()) {
					continue;
				}
				$name = $packageVendor->getFilename() . '/' . $packageName->getFilename();
				$path = (string) $packageName;
				if ($this->fileSystem->isSymlinkedDirectory($path) || $this->fileSystem->isJunction($path)) {
					$this->io->write("Composer cleaner: Skipped symlinked/junction package $name", true, IOInterface::VERBOSE);
					continue;
				}
				$ignore = $ignorePaths[$name] ?? null;
				if ($ignore === true) {
					$this->io->write("Composer cleaner: Skipped package $name", true, IOInterface::VERBOSE);
				} else {
					$this->io->write("Composer cleaner: Package $name", true, IOInterface::VERBOSE);
					$this->processPackage($path, (array) $ignore);
				}
			}
		}

		$this->io->write("Composer cleaner: Removed $this->removedCount files or directories.");
	}


	private function processPackage(string $packageDir, array $ignoreFiles): void
	{
		$data = $this->loadComposerJson($packageDir);
		$type = $data->type ?? null;
		if (!$data || !in_array($type, self::$allowedComposerTypes, true)) {
			return;
		}

		foreach ($this->getExcludes($data) as $exclude) {
			$dir = trim(ltrim($exclude, '.'), '/');
			if ($dir && !str_contains($dir, '..') && !self::matchMask($dir, $ignoreFiles)) {
				$path = $packageDir . '/' . $dir;
				$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
				$this->fileSystem->remove($path);
				$this->removedCount++;
			}
		}

		foreach ($this->getSources($data) as $source) {
			$dir = strstr(ltrim(ltrim($source, '.'), '/') . '/', '/', true);
			$ignoreFiles[] = $dir;
		}

		if (!$ignoreFiles || self::matchMask('', $ignoreFiles)) {
			return;
		}

		$ignoreFiles = array_merge($ignoreFiles, self::$alwaysIgnore);

		foreach (new FilesystemIterator($packageDir) as $path) {
			$fileName = $path->getFilename();
			if (!self::matchMask($fileName, $ignoreFiles)) {
				$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
				$this->fileSystem->remove((string) $path);
				$this->removedCount++;
			}
		}
	}


	/**
	 * @param  string[]  $patterns
	 */
	public static function matchMask(string $fileName, array $patterns): bool
	{
		foreach ($patterns as $pattern) {
			if (fnmatch($pattern, $fileName)) {
				return true;
			}
		}

		return false;
	}


	/**
	 * @return string[]
	 */
	private function getSources(stdClass $data): array
	{
		if (empty($data->autoload)) {
			return [];
		}

		$sources = isset($data->bin) ? (array) $data->bin : [];

		foreach ($data->autoload as $type => $items) {
			if ($type === 'psr-0') {
				foreach ($items as $namespace => $paths) {
					$namespace = strtr($namespace, '\_', '//');
					foreach ((array) $paths as $path) {
						$sources[] = rtrim($path, '\/') . '/' . $namespace;
					}
				}
			} elseif ($type === 'psr-4') {
				foreach ($items as $paths) {
					$sources = array_merge($sources, (array) $paths);
				}
			} elseif ($type === 'classmap' || $type === 'files') {
				$sources = array_merge($sources, (array) $items);

			} elseif ($type === 'exclude-from-classmap') {
				// ignore

			} else {
				$this->io->writeError("unknown autoload type $type");
				return [];
			}
		}

		return $sources;
	}


	/**
	 * @return string[]
	 */
	private function getExcludes(stdClass $data): array
	{
		return empty($data->autoload->{'exclude-from-classmap'})
			? []
			: (array) $data->autoload->{'exclude-from-classmap'};
	}


	public function loadComposerJson(string $dir): ?stdClass
	{
		$file = $dir . '/composer.json';
		if (!is_file($file)) {
			$this->io->writeError("Composer cleaner: File $file not found.", true, IOInterface::VERBOSE);
			return null;
		}
		$data = json_decode(file_get_contents($file));
		if (!$data instanceof stdClass) {
			$this->io->writeError("Composer cleaner: Invalid $file.");
			return null;
		}
		return $data;
	}
}
