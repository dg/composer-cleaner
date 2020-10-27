<?php
declare(strict_types=1);

namespace DG\ComposerCleaner;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;


class Plugin implements PluginInterface, EventSubscriberInterface
{
	public function activate(Composer $composer, IOInterface $io)
	{
	}


	public function deactivate(Composer $composer, IOInterface $io)
	{
	}


	public function uninstall(Composer $composer, IOInterface $io)
	{
	}


	public static function getSubscribedEvents()
	{
		return [
			ScriptEvents::POST_UPDATE_CMD => 'clean',
			ScriptEvents::POST_INSTALL_CMD => 'clean',
		];
	}


	public function clean(Event $event)
	{
		$vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
		$extra = $event->getComposer()->getPackage()->getExtra();
		$ignorePaths = $extra['cleaner-ignore'] ?? (array) $event->getComposer()->getConfig()->get('cleaner-ignore');
		$fileSystem = new Filesystem(new ProcessExecutor($event->getIO()));
		$cleaner = new Cleaner($event->getIO(), $fileSystem);
		$cleaner->clean($vendorDir, $ignorePaths);
	}
}
