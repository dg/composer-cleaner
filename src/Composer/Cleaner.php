<?php

/**
 * Victor The Cleaner for Composer.
 *
 * Copyright (c) 2015 David Grudl (https://davidgrudl.com)
 */

namespace DG\Composer;

use Composer\IO\IOInterface;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
		$this->io->write("Removed $this->removedCount files.");
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

		foreach (new FileSystemIterator($packageDir) as $path) {
			$fileName = $path->getFileName();
			if (!isset($dirs[$fileName]) && strncasecmp($fileName, 'license', 7)) {
				$this->io->write("Removing $path", TRUE, $this->io::VERBOSE);
				$this->delete($path);
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

			} else {
				$this->io->writeError("unknown autoload type $type");
				return [];
			}
		}

		return $sources;
	}


	/**
	 * @return void
	 */
	private function delete($path)
	{
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			exec('attrib -R ' . escapeshellarg($path) . ' /D 2> nul');
			exec('attrib -R ' . escapeshellarg("$path/*") . ' /D /S 2> nul');
		}

		if (is_dir($path)) {
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
				if ($item->isDir()) {
					rmdir($item);
				} else {
					$this->removedCount++;
					unlink($item);
				}
			}
			rmdir($path);

		} elseif (is_file($path)) {
			$this->removedCount++;
			unlink($path);
		}
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
