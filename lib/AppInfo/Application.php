<?php

declare(strict_types=1);

namespace OCA\BookTailor\AppInfo;

use OCA\BookTailor\Listener\AssetWriteListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'booktailor';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeWrittenEvent::class, AssetWriteListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
