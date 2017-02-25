<?php

/**
 * Victor The Cleaner for Composer.
 *
 * Copyright (c) 2015 David Grudl (https://davidgrudl.com)
 */

namespace DG\Composer;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;


class Cleaner
{
	/** @var int */
	private $removedCount;

	/** @var bool */
	private $testMode = FALSE;


	/**
	 * @param bool
	 */
	public function __construct($testMode = FALSE)
	{
		$this->testMode = $testMode;
	}

	/**
	 * @return void
	 */
	public function clean($projectDir)
	{
		if ($this->testMode) {
			echo "Running in test mode.\n";
		}

		$this->removedCount = 0;
		$data = $this->loadComposerJson($projectDir);
		$vendorDir = isset($data->config->{'vendor-dir'}) ? $data->config->{'vendor-dir'} : 'vendor';
		$this->processVendorDir("$projectDir/$vendorDir");

		echo "Removed $this->removedCount files.\n";
	}

	/**
	 * @return void
	 */
	private function processVendorDir($vendorDir)
	{
		if (!is_dir($vendorDir)) {
			throw new Exception("Missing directory $vendorDir.");
		}

		foreach (new FileSystemIterator($vendorDir) as $packageVendor) {
			if (!$packageVendor->isDir()) {
				continue;
			}
			foreach (new FileSystemIterator($packageVendor) as $packageName) {
				if (!$packageName->isDir()) {
					continue;
				}
				echo "\nPACKAGE {$packageVendor->getFileName()}/{$packageName->getFileName()}\n";
				$this->processPackage((string) $packageName);
			}
		}
	}


	/**
	 * @return void
	 */
	private function processPackage($packageDir)
	{
		if (!is_file("$packageDir/composer.json")) {
			echo "missing composer.json\n";
			return;
		}
		$data = $this->loadComposerJson($packageDir);
		if (isset($data->type) && $data->type !== 'library') {
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
				echo "deleting $fileName\n";
				if (!$this->testMode) {
					$this->delete($path);
				}
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
						$sources[] = $path . strtr($namespace, '\\', '/');
					}
				}

			} elseif ($type === 'psr-4') {
				foreach ($items as $namespace => $paths) {
					$sources = array_merge($sources, (array) $paths);
				}

			} elseif ($type === 'classmap' || $type === 'files') {
				$sources = array_merge($sources, (array) $items);

			} else {
				echo "unknown autoload type $type\n";
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
	 * @return stdClass
	 */
	private function loadComposerJson($dir)
	{
		$file = $dir . '/composer.json';
		if (!is_file($file)) {
			throw new Exception("File $file not found.");
		}
		$data = json_decode(file_get_contents($file));
		if (!$data instanceof stdClass) {
			throw new Exception("Invalid $file.");
		}
		return $data;
	}

}
