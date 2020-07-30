<?php
declare(strict_types=1);

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
	/** @var IOInterface */
	private $io;

	/** @var Filesystem */
	private $fileSystem;

	/** @var int */
	private $removedCount = 0;

	/** @var array */
	private static $allowedComposerTypes = [null, 'library', 'composer-plugin'];

	/** @var string[] */
	private static $alwaysIgnore = ['composer.json', 'license*', 'LICENSE*', '.phpstorm.meta.php'];


	public function __construct(IOInterface $io, Filesystem $fileSystem)
	{
		$this->io = $io;
		$this->fileSystem = $fileSystem;
	}


	/**
	 * @return void
	 */
	public function clean($vendorDir, array $ignorePaths = [])
	{
		foreach (new FilesystemIterator($vendorDir) as $packageVendor) {
			if (!$packageVendor->isDir()) {
				continue;
			}
			foreach (new FilesystemIterator((string) $packageVendor) as $packageName) {
				if (!$packageName->isDir()) {
					continue;
				}
				$name = $packageVendor->getFileName() . '/' . $packageName->getFileName();
				$ignore = $ignorePaths[$name] ?? null;
				if ($ignore === true) {
					$this->io->write("Composer cleaner: Skipped package $name", true, IOInterface::VERBOSE);
				} else {
					$this->io->write("Composer cleaner: Package $name", true, IOInterface::VERBOSE);
					$this->processPackage((string) $packageName, (array) $ignore);
				}
			}
		}
		$this->io->write("Composer cleaner: Removed $this->removedCount files or directories.");
	}


	/**
	 * @return void
	 */
	private function processPackage($packageDir, array $ignoreFiles)
	{
		$data = $this->loadComposerJson($packageDir);
		$type = isset($data->type) ? $data->type : null;
		if (!$data || !in_array($type, self::$allowedComposerTypes, true)) {
			return;
		}

		foreach ($this->getExcludes($data) as $exclude) {
			$dir = trim(ltrim($exclude, '.'), '/');
			if ($dir && strpos($dir, '..') === false && !self::matchMask($dir, $ignoreFiles)) {
				$path = $packageDir . '/' . $dir;
				$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
				$this->fileSystem->remove($path);
				$this->removedCount++;
			}
		}

		$toIgnore = [];

		foreach ($this->getSources($data) as $source) {
			$dir = '/' . strstr(ltrim(ltrim($source, '.'), '/') . '/', '/', true);

			if (is_dir($packageDir . $dir)) {
				$dir .= '/*';
			}

			$toIgnore[] = $dir;
		}

		$toIgnore = array_merge($toIgnore, $ignoreFiles);

		if (!$toIgnore || self::matchMask('', $toIgnore)) {
			return;
		}

		$toIgnore = array_merge($toIgnore, array_map(function ($v) {
			return '/' . ltrim($v, '/');
		}, self::$alwaysIgnore));

		foreach ($this->collectPaths($packageDir, $toIgnore) as $path) {
			$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
			$this->fileSystem->remove($path);
			$this->removedCount++;
		}
	}


	/**
	 * @param  string
	 * @param  string[]
	 * @param  string
	 * @return string[]|bool
	 */
	private function collectPaths($directory, $ignorePaths, $subdir = '')
	{
		$list = [];
		$iterator = dir($directory . $subdir);
		$removeAll = true;
		while (($entry = $iterator->read()) !== false) {
			$path = "$directory$subdir/$entry";
			$short = "$subdir/$entry";

			if ($entry == '.' || $entry == '..') {
				continue;

			} elseif (self::matchMask($short, $ignorePaths, is_dir($path))) {
				$removeAll = false;
				continue;

			} elseif (is_dir($path)) {
				$removeChildren = $this->collectPaths($directory, $ignorePaths, $short);

				if ($removeChildren === true) {
					$list[$short . '/'] = $path;

				} else {
					$list += $removeChildren;
					$removeAll = false;
				}

			} elseif (is_file($path)) {
				$list[$short] = $path;
			}
		}
		$iterator->close();
		return ($subdir !== '' && $removeAll) ? true : $list;
	}


	/**
	 * @param  string
	 * @param  string[]
	 * @return bool
	 */
	public static function matchMask($fileName, array $patterns, $isDir = false)
	{
		$res = false;
		$path = explode('/', ltrim($fileName, '/'));
		foreach ($patterns as $pattern) {
			$pattern = strtr($pattern, '\\', '/');
			if ($neg = substr($pattern, 0, 1) === '!') {
				$pattern = substr($pattern, 1);
			}

			if (strpos($pattern, '/') === false) { // no slash means base name
				if (fnmatch($pattern, end($path), FNM_CASEFOLD)) {
					$res = !$neg;
				}
				continue;

			} elseif (substr($pattern, -1) === '/') { // trailing slash means directory
				$pattern = trim($pattern, '/');
				if (!$isDir && count($path) <= count(explode('/', $pattern))) {
					continue;
				}
			}

			$parts = explode('/', ltrim($pattern, '/'));

			if (fnmatch(
				implode('/', $neg && $isDir ? array_slice($parts, 0, count($path)) : $parts),
				implode('/', array_slice($path, 0, count($parts))),
				FNM_CASEFOLD | FNM_PATHNAME
			)) {
				$res = !$neg;
			}
		}
		return $res;
	}


	/**
	 * @return string[]
	 */
	private function getSources(stdClass $data)
	{
		if (empty($data->autoload)) {
			return [];
		}

		$sources = isset($data->bin) ? (array) $data->bin : [];

		foreach ($data->autoload as $type => $items) {
			if ($type === 'psr-0') {
				foreach ($items as $namespace => $paths) {
					$namespace = strtr($namespace, '\\_', '//');
					foreach ((array) $paths as $path) {
						$sources[] = rtrim($path, '\\/') . '/' . $namespace;
					}
				}

			} elseif ($type === 'psr-4') {
				foreach ($items as $namespace => $paths) {
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
	private function getExcludes(stdClass $data)
	{
		return empty($data->autoload->{'exclude-from-classmap'})
			? []
			: (array) $data->autoload->{'exclude-from-classmap'};
	}


	/**
	 * @return stdClass|null
	 */
	public function loadComposerJson($dir)
	{
		$file = $dir . '/composer.json';
		if (!is_file($file)) {
			$this->io->writeError("Composer cleaner: File $file not found.", true, IOInterface::VERBOSE);
			return;
		}
		$data = json_decode(file_get_contents($file));
		if (!$data instanceof stdClass) {
			$this->io->writeError("Composer cleaner: Invalid $file.");
			return;
		}
		return $data;
	}
}
