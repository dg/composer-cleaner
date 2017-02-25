<?php

/**
 * Victor The Cleaner for Composer.
 *
 * Copyright (c) 2015 David Grudl (https://davidgrudl.com)
 */

namespace DG\Composer;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use FilesystemIterator;
use stdClass;


class Cleaner
{
	/** @var IOInterface */
	private $io;

	/** @var int */
	private $removedCount = 0;


	public function __construct(IOInterface $io)
	{
		$this->io = $io;
	}


	/**
	 * @return void
	 */
	public function clean($vendorDir)
	{
		foreach (new FileSystemIterator($vendorDir) as $packageVendor) {
			if (!$packageVendor->isDir()) {
				continue;
			}
			foreach (new FileSystemIterator($packageVendor) as $packageName) {
				if (!$packageName->isDir()) {
					continue;
				}
				$this->io->write("Package {$packageVendor->getFileName()}/{$packageName->getFileName()}", TRUE, $this->io::VERBOSE);
				$this->processPackage((string) $packageName);
			}
		}
		$this->io->write("Removed $this->removedCount files or directories.");
	}


	/**
	 * @return void
	 */
	private function processPackage($packageDir)
	{
		$data = $this->loadComposerJson($packageDir);
		if (!$data || (isset($data->type) && $data->type !== 'library')) {
			return;
		}

		$dirs = [];
		foreach ($this->getSources($data) as $source) {
			$dir = strstr(ltrim(ltrim($source, '.'), '/') . '/', '/', TRUE);
			$dirs[$dir] = TRUE;
		}

		if (!$dirs || isset($dirs[''])) {
			return;
		}

		$dirs['composer.json'] = TRUE;
		$fileSystem = new Filesystem(new ProcessExecutor($this->io));

		foreach (new FileSystemIterator($packageDir) as $path) {
			$fileName = $path->getFileName();
			if (!isset($dirs[$fileName]) && strncasecmp($fileName, 'license', 7)) {
				$this->io->write("Removing $path", TRUE, $this->io::VERBOSE);
				$fileSystem->remove($path);
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
					foreach ((array) $paths as $path) {
						$sources[] = $path . strtr($namespace, '\\_', '//');
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
	 * @return stdClass|NULL
	 */
	private function loadComposerJson($dir)
	{
		$file = $dir . '/composer.json';
		if (!is_file($file)) {
			$this->io->writeError("File $file not found.", TRUE, $this->io::VERBOSE);
			return;
		}
		$data = json_decode(file_get_contents($file));
		if (!$data instanceof stdClass) {
			$this->io->writeError("Invalid $file.");
			return;
		}
		return $data;
	}

}
