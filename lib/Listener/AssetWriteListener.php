<?php

declare(strict_types=1);

namespace OCA\BookTailor\Listener;

use DateTime;
use OCA\BookTailor\Db\AssetMapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<NodeWrittenEvent>
 */
class AssetWriteListener implements IEventListener {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly AssetMapper $assetMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeWrittenEvent) {
			return;
		}

		try {
			$node = $event->getNode();
			if (!$node instanceof File) {
				return;
			}

			$asset = $this->assetMapper->findByFileId($node->getId());
			if ($asset === null) {
				return;
			}

			$writtenAt = $node->getMTime();
			if ($asset->getUpdatedAt()->getTimestamp() >= $writtenAt) {
				return;
			}

			$asset->setUpdatedAt((new DateTime())->setTimestamp($writtenAt));
			$this->assetMapper->update($asset);
		} catch (\Throwable $e) {
			$this->logger->warning('Could not update the asset after a file write', [
				'exception' => $e,
			]);
		}
	}
}
