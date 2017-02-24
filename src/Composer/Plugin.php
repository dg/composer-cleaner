<?php

namespace DG\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;


class Plugin implements PluginInterface, EventSubscriberInterface
{

	public function activate(Composer $composer, IOInterface $io)
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
		$cleaner = new Cleaner($event->getIO());
		$cleaner->clean($vendorDir);
	}

}
