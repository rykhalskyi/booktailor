<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

class AiProviderRegistry {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly OpenAiCompatibleProvider $openAiCompatibleProvider,
	) {
	}

	public function provider(string $provider): AiProviderInterface {
		if (!AiProviderCatalog::supports($provider)) {
			throw new AiProviderException('Unsupported AI provider');
		}

		return $this->openAiCompatibleProvider;
	}

	/**
	 * @return list<array{id: string, label: string, defaultModel: string}>
	 */
	public function list(): array {
		$providers = [];
		foreach (AiProviderCatalog::providers() as $id => $config) {
			$providers[] = [
				'id' => $id,
				'label' => $config['label'],
				'defaultModel' => $config['defaultModel'],
			];
		}

		return $providers;
	}
}
