<?php

declare(strict_types=1);

namespace OCA\BookTailor\Listener;

use OCA\BookTailor\Db\BookShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<UserDeletedEvent>
 */
class UserDeletedListener implements IEventListener {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookShareMapper $shareMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserDeletedEvent) {
			return;
		}

		try {
			$this->shareMapper->deleteBySharedWith($event->getUid());
		} catch (\Throwable $e) {
			$this->logger->warning('Could not remove BookTailor shares for a deleted user', [
				'exception' => $e,
			]);
		}
	}
}
