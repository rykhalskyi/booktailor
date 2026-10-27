<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCP\IConfig;

class AiSettingsService {
	private const CONTENT_LOGGING = 'ai_content_logging';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IConfig $config,
		private readonly LangfuseService $langfuseService,
	) {
	}

	public function contentLogging(string $userId): bool {
		return $this->config->getUserValue($userId, 'booktailor', self::CONTENT_LOGGING, '0') === '1';
	}

	public function setContentLogging(string $userId, bool $enabled): void {
		$this->config->setUserValue($userId, 'booktailor', self::CONTENT_LOGGING, $enabled ? '1' : '0');
	}

	/**
	 * @return array{configured: bool, url: ?string}
	 */
	public function langfuseStatus(): array {
		return [
			'configured' => $this->langfuseService->isConfigured(),
			'url' => $this->langfuseService->publicUrl(),
		];
	}
}
