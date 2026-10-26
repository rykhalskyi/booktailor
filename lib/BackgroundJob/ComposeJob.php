<?php

declare(strict_types=1);

namespace OCA\BookTailor\BackgroundJob;

use OCA\BookTailor\Service\CompositionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ComposeJob extends QueuedJob {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		ITimeFactory $time,
		private readonly CompositionService $compositionService,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param mixed $argument
	 */
	protected function run($argument): void {
		$compositionId = is_array($argument) ? ($argument['compositionId'] ?? null) : null;
		if (!is_int($compositionId) && !is_string($compositionId)) {
			$this->logger->warning('BookTailor compose job has no composition id');

			return;
		}

		$compositionId = (int)$compositionId;
		if ($compositionId <= 0) {
			$this->logger->warning('BookTailor compose job has an invalid composition id');

			return;
		}

		$this->compositionService->run($compositionId);
	}
}
