<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

class AiCompletion {
	/**
	 * @param array<string, mixed> $usage
	 */
	public function __construct(
		public readonly string $content,
		public readonly string $model,
		public readonly array $usage = [],
	) {
	}
}
