<?php

declare(strict_types=1);

namespace OCA\BookTailor\Listener;

use OCA\BookTailor\Db\BookShareMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Share\Events\ShareDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<ShareDeletedEvent>
 */
class ShareDeletedListener implements IEventListener {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookShareMapper $shareMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof ShareDeletedEvent) {
			return;
		}

		try {
			$this->shareMapper->deleteByNextcloudShareId($event->getShare()->getFullId());
		} catch (\Throwable $e) {
			$this->logger->warning('Could not remove a BookTailor share after its Nextcloud share was deleted', [
				'exception' => $e,
			]);
		}
	}
}
