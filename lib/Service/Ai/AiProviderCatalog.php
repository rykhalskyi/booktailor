<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

class AiProviderCatalog {
	/**
	 * @return array<string, array{label: string, defaultModel: string}>
	 */
	public static function providers(): array {
		return [
			'deepseek' => [
				'label' => 'DeepSeek',
				'defaultModel' => 'deepseek-chat',
			],
			'siliconflow' => [
				'label' => 'SiliconFlow',
				'defaultModel' => 'Qwen/Qwen3-8B',
			],
		];
	}

	public static function supports(string $provider): bool {
		return array_key_exists($provider, self::providers());
	}

	public static function defaultModel(string $provider): string {
		return self::providers()[$provider]['defaultModel'] ?? '';
	}

}
