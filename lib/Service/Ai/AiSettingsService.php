<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCA\BookTailor\Exception\ValidationException;
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

	/**
	 * Full Langfuse app-level configuration for administrators. The secret key
	 * is write-only: only whether one is stored is returned.
	 *
	 * @return array{configured: bool, url: ?string, publicKey: ?string, promptLabel: string, secretConfigured: bool, sources: array{url: string, publicKey: string, secretKey: string, promptLabel: string}}
	 */
	public function langfuseSettings(): array {
		$url = $this->langfuseService->baseUrl();
		$publicKey = $this->langfuseService->publicKey();

		return [
			'configured' => $this->langfuseService->isConfigured(),
			'url' => $url === '' ? null : $url,
			'publicKey' => $publicKey === '' ? null : $publicKey,
			'promptLabel' => $this->langfuseService->promptLabel(),
			'secretConfigured' => $this->langfuseService->hasSecret(),
			'sources' => $this->langfuseService->sources(),
		];
	}

	/**
	 * Update Langfuse app-level settings. A null value keeps the current value;
	 * an empty string clears it. The secret key is only changed when provided.
	 *
	 * @psalm-suppress DeprecatedMethod
	 */
	public function updateLangfuseSettings(
		?string $url = null,
		?string $publicKey = null,
		?string $secretKey = null,
		?string $promptLabel = null,
	): void {
		if ($url !== null) {
			$url = trim($url);
			if ($url === '') {
				$this->config->deleteAppValue('booktailor', LangfuseService::CONFIG_URL);
			} else {
				$this->assertValidUrl($url);
				$this->config->setAppValue('booktailor', LangfuseService::CONFIG_URL, $url);
			}
		}

		if ($publicKey !== null) {
			$this->storeOrRemove(LangfuseService::CONFIG_PUBLIC_KEY, $publicKey);
		}

		if ($secretKey !== null) {
			$this->langfuseService->storeSecret($secretKey);
		}

		if ($promptLabel !== null) {
			$this->storeOrRemove(LangfuseService::CONFIG_PROMPT_LABEL, $promptLabel);
		}
	}

	/** @psalm-suppress DeprecatedMethod */
	public function clearLangfuseSettings(): void {
		foreach ([
			LangfuseService::CONFIG_URL,
			LangfuseService::CONFIG_PUBLIC_KEY,
			LangfuseService::CONFIG_PROMPT_LABEL,
		] as $key) {
			$this->config->deleteAppValue('booktailor', $key);
		}

		$this->langfuseService->clearSecret();
	}

	/** @psalm-suppress DeprecatedMethod */
	private function storeOrRemove(string $key, string $value): void {
		$value = trim($value);
		if ($value === '') {
			$this->config->deleteAppValue('booktailor', $key);

			return;
		}

		$this->config->setAppValue('booktailor', $key, $value);
	}

	private function assertValidUrl(string $url): void {
		$parts = parse_url($url);
		if ($parts === false
			|| !isset($parts['scheme'], $parts['host'])
			|| !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
			|| trim($parts['host']) === ''
			|| preg_match('/\s/', $url) === 1) {
			throw new ValidationException('Langfuse URL must be a valid http(s) URL.');
		}
	}
}
