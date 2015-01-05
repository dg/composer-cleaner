<?php

/**
 * Victor The Cleaner for Composer.
 *
 * Copyright (c) 2015 David Grudl (http://davidgrudl.com)
 */


class Cleaner
{
	/** @var int */
	private $removed = 0;


	/**
	 * @return void
	 */
	public function clean($path = '.')
	{
		$data = $this->loadComposerJson($path);

		$this->scanVendorDir(isset($data->config->{'vendor-dir'})
			? $data->config->{'vendor-dir'}
			: 'vendor'
		);

		echo "Removed $this->removed files.\n";
	}


	/**
	 * @return void
	 */
	private function scanVendorDir($vendorDir)
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
				$this->processPackage((string) $packageName);
			}
		}
	}


	/**
	 * @return void
	 */
	private function processPackage($packageDir)
	{
		echo "\nPACKAGE $packageDir\n";
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
			if (!isset($dirs[$path->getFileName()])) {
				echo "deleting {$path->getFileName()}\n";
				$this->delete($path);
			}
		}
	}


	/**
	 * @return strings[]
	 */
	private function getSources(stdClass $data)
	{
		if (empty($data->autoload)) {
			return [];
		}

		$sources = isset($data->bin) ? (array) $data->bin : [];

		foreach ($data->autoload as $type => $items) {
			if ($type === 'psr-0' || $type === 'psr-4') {
				foreach ($items as $namespace => $paths) {
					foreach ((array) $paths as $path) {
						$sources[] = $path . strtr($namespace, '\\', '/');
					}
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
			exec('attrib -R ' . escapeshellarg($path) . ' /D');
			exec('attrib -R ' . escapeshellarg("$path/*") . ' /D /S');
		}

		if (is_dir($path)) {
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
				if ($item->isDir()) {
					rmdir($item);
				} else {
					$this->removed++;
					unlink($item);
				}
			}
			rmdir($path);

		} elseif (is_file($path)) {
			$this->removed++;
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
		if (is_array($data)) {
			throw new Exception("Invalid $file.");
		}
		return $data;
	}

}
