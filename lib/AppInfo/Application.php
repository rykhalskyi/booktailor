<?php

declare(strict_types=1);

namespace OCA\BookTailor\AppInfo;

use OCA\BookTailor\Listener\AssetWriteListener;
use OCA\BookTailor\Listener\ShareDeletedListener;
use OCA\BookTailor\Listener\UserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'booktailor';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(NodeWrittenEvent::class, AssetWriteListener::class);
		$context->registerEventListener(ShareDeletedEvent::class, ShareDeletedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
