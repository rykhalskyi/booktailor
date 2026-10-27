<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

interface AiProviderInterface {
	/**
	 * @param list<array{role: string, content: string}> $messages
	 * @param array<string, mixed> $options
	 */
	public function complete(
		string $apiKey,
		string $model,
		array $messages,
		array $options = [],
	): AiCompletion;
}
