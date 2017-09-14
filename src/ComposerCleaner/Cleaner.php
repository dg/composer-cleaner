<?php

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
		foreach (new FileSystemIterator($vendorDir) as $packageVendor) {
			if (!$packageVendor->isDir()) {
				continue;
			}
			foreach (new FileSystemIterator($packageVendor) as $packageName) {
				if (!$packageName->isDir()) {
					continue;
				}
				$name = $packageVendor->getFileName() . '/' . $packageName->getFileName();
				$ignore = isset($ignorePaths[$name]) ? (array) $ignorePaths[$name] : [];
				$this->io->write("Composer cleaner: Package $name", true, IOInterface::VERBOSE);
				$this->processPackage((string) $packageName, $ignore);
			}
		}
		$this->io->write("Composer cleaner: Removed $this->removedCount files or directories.");
	}


	/**
	 * @return void
	 */
	private function processPackage($packageDir, array $ignorePaths)
	{
		$data = $this->loadComposerJson($packageDir);
		$type = isset($data->type) ? $data->type : null;
		if (!$data || !in_array($type, self::$allowedComposerTypes, true)) {
			return;
		}

		$paths = array_fill_keys($ignorePaths, true);

		if (isset($paths['*'])) {
			$this->io->write("Composer cleaner: Ignoring whole package $packageDir", true, IOInterface::VERBOSE);
			return;
		}

		foreach ($this->getExcludes($data) as $exclude) {
			$dir = trim(ltrim($exclude, '.'), '/');
			if ($dir && strpos($dir, '..') === false && !isset($paths[$dir])) {
				$path = $packageDir . '/' . $dir;
				$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
				$this->fileSystem->remove($path);
				$this->removedCount++;
			}
		}

		foreach ($this->getSources($data) as $source) {
			$dir = strstr(ltrim(ltrim($source, '.'), '/') . '/', '/', true);
			$paths[$dir] = true;
		}

		if (!$paths || isset($paths[''])) {
			return;
		}

		$paths['composer.json'] = true;

		foreach (new FileSystemIterator($packageDir) as $path) {
			$fileName = $path->getFileName();
			if (!isset($paths[$fileName]) && strncasecmp($fileName, 'license', 7)) {
				$this->io->write("Composer cleaner: Removing $path", true, IOInterface::VERBOSE);
				$this->fileSystem->remove($path);
				$this->removedCount++;
			}
		}
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
