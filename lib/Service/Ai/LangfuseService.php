<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCA\BookTailor\Exception\ValidationException;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class LangfuseService {
	public const CONFIG_URL = 'langfuse_url';
	public const CONFIG_PUBLIC_KEY = 'langfuse_public_key';
	public const CONFIG_SECRET_KEY = 'langfuse_secret_key';
	public const CONFIG_SECRET_KEY_ENCRYPTED = 'langfuse_secret_key_encrypted';
	public const CONFIG_PROMPT_LABEL = 'langfuse_prompt_label';

	public const ENV_URL = 'BOOKTAILOR_LANGFUSE_URL';
	public const ENV_PUBLIC_KEY = 'BOOKTAILOR_LANGFUSE_PUBLIC_KEY';
	public const ENV_SECRET_KEY = 'BOOKTAILOR_LANGFUSE_SECRET_KEY';
	public const ENV_PROMPT_LABEL = 'BOOKTAILOR_LANGFUSE_PROMPT_LABEL';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IClientService $clientService,
		private readonly IConfig $config,
		private readonly LoggerInterface $logger,
		private readonly ICrypto $crypto,
	) {
	}

	public function isConfigured(): bool {
		return $this->baseUrl() !== ''
			&& $this->publicKey() !== ''
			&& $this->secretKey() !== '';
	}

	/**
	 * Where each effective value comes from, so the settings UI can explain
	 * when a value is inherited from the server environment rather than stored
	 * in app config.
	 *
	 * @return array{url: string, publicKey: string, secretKey: string, promptLabel: string}
	 */
	public function sources(): array {
		return [
			'url' => $this->source(self::CONFIG_URL, [self::ENV_URL, 'LANGFUSE_HOST', 'LANGFUSE_URL']),
			'publicKey' => $this->source(self::CONFIG_PUBLIC_KEY, [self::ENV_PUBLIC_KEY, 'LANGFUSE_PUBLIC_KEY']),
			'secretKey' => $this->secretSource(),
			'promptLabel' => $this->source(self::CONFIG_PROMPT_LABEL, [self::ENV_PROMPT_LABEL, 'LANGFUSE_PROMPT_LABEL']),
		];
	}

	/** @psalm-suppress DeprecatedMethod */
	public function baseUrl(): string {
		return rtrim(trim($this->value(self::CONFIG_URL, [self::ENV_URL, 'LANGFUSE_HOST', 'LANGFUSE_URL'])), '/');
	}

	public function publicUrl(): ?string {
		return $this->isConfigured() ? $this->baseUrl() : null;
	}

	/** @psalm-suppress DeprecatedMethod */
	public function promptLabel(): string {
		$label = trim($this->value(self::CONFIG_PROMPT_LABEL, [self::ENV_PROMPT_LABEL, 'LANGFUSE_PROMPT_LABEL'], 'production'));

		return $label === '' ? 'production' : $label;
	}

	/**
	 * Fetch a text prompt by name. Returns null when Langfuse is unavailable or
	 * the prompt is not a text prompt; callers must use the local fallback.
	 *
	 * @return array{text: string, version: ?int}|null
	 */
	public function fetchPrompt(string $name): ?array {
		if (!$this->isConfigured()) {
			return null;
		}

		try {
			$url = $this->baseUrl() . '/api/public/v2/prompts/' . rawurlencode($name)
				. '?label=' . rawurlencode($this->promptLabel());
			$response = $this->clientService->newClient()->get($url, [
				'headers' => $this->headers(),
				'connect_timeout' => 2,
				'timeout' => 4,
			]);

			if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
				$this->logger->warning('BookTailor Langfuse prompt fetch failed', [
					'status' => $response->getStatusCode(),
					'prompt' => $name,
				]);

				return null;
			}

			$decoded = json_decode((string)$response->getBody(), true);
			if (!is_array($decoded)) {
				return null;
			}

			/** @var array<string, mixed> $decoded */
			$text = $this->extractPromptText($decoded);
			if ($text === null || trim($text) === '') {
				return null;
			}

			/** @var mixed $version */
			$version = $decoded['version'] ?? null;

			return [
				'text' => $text,
				'version' => is_int($version) ? $version : (is_string($version) && is_numeric($version) ? (int)$version : null),
			];
		} catch (\Throwable $e) {
			$this->logger->warning('BookTailor Langfuse prompt fetch failed', [
				'prompt' => $name,
				'exceptionClass' => $e::class,
			]);

			return null;
		}
	}

	/**
	 * Send one generation to Langfuse through OTLP. Best-effort: never throws.
	 *
	 * Langfuse v4 has no separate trace input/output: the overall request and
	 * response belong on the root observation, so they are set on the root span
	 * as well as on the generation.
	 *
	 * @param array<string, mixed> $usage
	 */
	public function traceGeneration(
		string $userId,
		string $traceName,
		string $model,
		string $input,
		string $output,
		?string $promptName = null,
		?int $promptVersion = null,
		array $usage = [],
		bool $contentLogging = false,
		?int $startTimeUnixNano = null,
		?int $endTimeUnixNano = null,
	): ?string {
		if (!$this->isConfigured()) {
			return null;
		}

		$traceId = bin2hex(random_bytes(16));
		$rootSpanId = bin2hex(random_bytes(8));
		$generationSpanId = bin2hex(random_bytes(8));
		$start = $startTimeUnixNano ?? (int)round(microtime(true) * 1000000000.0);
		$end = $endTimeUnixNano ?? $start;
		if ($end < $start) {
			$end = $start;
		}

		$traceAttributes = [
			['key' => 'langfuse.trace.name', 'value' => ['stringValue' => $traceName]],
			['key' => 'langfuse.user.id', 'value' => ['stringValue' => $userId]],
			['key' => 'langfuse.observation.type', 'value' => ['stringValue' => 'span']],
		];
		$generationAttributes = [
			['key' => 'langfuse.trace.name', 'value' => ['stringValue' => $traceName]],
			['key' => 'langfuse.user.id', 'value' => ['stringValue' => $userId]],
			['key' => 'langfuse.observation.type', 'value' => ['stringValue' => 'generation']],
			['key' => 'langfuse.observation.model.name', 'value' => ['stringValue' => $model]],
		];
		if ($contentLogging) {
			$traceAttributes[] = ['key' => 'langfuse.observation.input', 'value' => ['stringValue' => $this->truncate($input)]];
			$traceAttributes[] = ['key' => 'langfuse.observation.output', 'value' => ['stringValue' => $this->truncate($output)]];
			$generationAttributes[] = ['key' => 'langfuse.observation.input', 'value' => ['stringValue' => $this->truncate($input)]];
			$generationAttributes[] = ['key' => 'langfuse.observation.output', 'value' => ['stringValue' => $this->truncate($output)]];
		}
		if ($usage !== []) {
			$generationAttributes[] = [
				'key' => 'langfuse.observation.usage_details',
				'value' => ['stringValue' => (string)json_encode($usage, JSON_UNESCAPED_SLASHES)],
			];
		}
		if ($promptName !== null && $promptName !== '') {
			$generationAttributes[] = ['key' => 'langfuse.observation.prompt.name', 'value' => ['stringValue' => $promptName]];
			if ($promptVersion !== null) {
				$generationAttributes[] = ['key' => 'langfuse.observation.prompt.version', 'value' => ['intValue' => (string)$promptVersion]];
			}
		}

		$payload = [
			'resourceSpans' => [[
				'resource' => [
					'attributes' => [
						['key' => 'service.name', 'value' => ['stringValue' => 'booktailor']],
					],
				],
				'scopeSpans' => [[
					'scope' => ['name' => 'booktailor-ai'],
					'spans' => [
						[
							'traceId' => $traceId,
							'spanId' => $rootSpanId,
							'name' => $traceName,
							'kind' => 1,
							'startTimeUnixNano' => (string)$start,
							'endTimeUnixNano' => (string)$end,
							'attributes' => $traceAttributes,
						],
						[
							'traceId' => $traceId,
							'spanId' => $generationSpanId,
							'parentSpanId' => $rootSpanId,
							'name' => $traceName . ' generation',
							'kind' => 1,
							'startTimeUnixNano' => (string)$start,
							'endTimeUnixNano' => (string)$end,
							'attributes' => $generationAttributes,
						],
					],
				]],
			]],
		];

		try {
			$response = $this->clientService->newClient()->post(
				$this->baseUrl() . '/api/public/otel/v1/traces',
				[
					'headers' => $this->headers() + ['x-langfuse-ingestion-version' => '4'],
					'body' => (string)json_encode($payload, JSON_UNESCAPED_SLASHES),
					'connect_timeout' => 2,
					'timeout' => 4,
				],
			);
			if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
				$this->logger->warning('BookTailor Langfuse trace failed', [
					'status' => $response->getStatusCode(),
					'traceId' => $traceId,
				]);

				return null;
			}

			return $traceId;
		} catch (\Throwable $e) {
			$this->logger->warning('BookTailor Langfuse trace failed', [
				'traceId' => $traceId,
				'exceptionClass' => $e::class,
			]);

			return null;
		}
	}

	/**
	 * @return array<string, string>
	 */
	private function headers(): array {
		return [
			'Authorization' => 'Basic ' . base64_encode($this->publicKey() . ':' . $this->secretKey()),
			'Content-Type' => 'application/json',
		];
	}

	/** @psalm-suppress DeprecatedMethod */
	public function publicKey(): string {
		return trim($this->value(self::CONFIG_PUBLIC_KEY, [self::ENV_PUBLIC_KEY, 'LANGFUSE_PUBLIC_KEY']));
	}

	public function hasSecret(): bool {
		return $this->secretKey() !== '';
	}

	/**
	 * Store the secret encrypted at rest. An empty value clears any stored
	 * secret. The legacy plaintext app-config key is removed so it cannot
	 * shadow the encrypted value later.
	 *
	 * @psalm-suppress DeprecatedMethod
	 */
	public function storeSecret(string $secret): void {
		$secret = trim($secret);
		if ($secret === '') {
			$this->clearSecret();

			return;
		}

		try {
			$encrypted = $this->crypto->encrypt($secret);
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor could not encrypt the Langfuse secret key', [
				'exceptionClass' => $e::class,
			]);
			throw new ValidationException('The Langfuse secret key could not be stored securely');
		}

		$this->config->setAppValue('booktailor', self::CONFIG_SECRET_KEY_ENCRYPTED, $encrypted);
		$this->config->deleteAppValue('booktailor', self::CONFIG_SECRET_KEY);
	}

	/** @psalm-suppress DeprecatedMethod */
	public function clearSecret(): void {
		$this->config->deleteAppValue('booktailor', self::CONFIG_SECRET_KEY_ENCRYPTED);
		$this->config->deleteAppValue('booktailor', self::CONFIG_SECRET_KEY);
	}

	/** @psalm-suppress DeprecatedMethod */
	private function secretKey(): string {
		$encrypted = trim($this->config->getAppValue('booktailor', self::CONFIG_SECRET_KEY_ENCRYPTED, ''));
		if ($encrypted !== '') {
			try {
				return trim($this->crypto->decrypt($encrypted));
			} catch (\Throwable $e) {
				$this->logger->warning('BookTailor could not decrypt the Langfuse secret key', [
					'exceptionClass' => $e::class,
				]);
			}
		}

		$legacy = trim($this->config->getAppValue('booktailor', self::CONFIG_SECRET_KEY, ''));
		if ($legacy !== '') {
			return $legacy;
		}

		return $this->envValue([self::ENV_SECRET_KEY, 'LANGFUSE_SECRET_KEY']) ?? '';
	}

	/**
	 * @param list<string> $envKeys
	 */
	private function source(string $configKey, array $envKeys): string {
		return match ($this->resolve($configKey, $envKeys)['source']) {
			'app' => 'app',
			'environment' => 'environment',
			default => 'none',
		};
	}

	/** @psalm-suppress DeprecatedMethod */
	private function secretSource(): string {
		if (trim($this->config->getAppValue('booktailor', self::CONFIG_SECRET_KEY_ENCRYPTED, '')) !== ''
			|| trim($this->config->getAppValue('booktailor', self::CONFIG_SECRET_KEY, '')) !== '') {
			return 'app';
		}

		return $this->envValue([self::ENV_SECRET_KEY, 'LANGFUSE_SECRET_KEY']) !== null ? 'environment' : 'none';
	}

	/**
	 * App config wins; environment variables are the no-occ fallback. Both the
	 * BookTailor-prefixed names and the standard LANGFUSE_* names are accepted.
	 *
	 * @param list<string> $envKeys
	 * @psalm-suppress DeprecatedMethod
	 */
	private function value(string $configKey, array $envKeys, string $default = ''): string {
		return $this->resolve($configKey, $envKeys, $default)['value'];
	}

	/**
	 * @param list<string> $envKeys
	 * @return array{value: string, source: 'app'|'environment'|'default'}
	 * @psalm-suppress DeprecatedMethod
	 */
	private function resolve(string $configKey, array $envKeys, string $default = ''): array {
		$configured = trim($this->config->getAppValue('booktailor', $configKey, ''));
		if ($configured !== '') {
			return ['value' => $configured, 'source' => 'app'];
		}

		$fromEnv = $this->envValue($envKeys);
		if ($fromEnv !== null) {
			return ['value' => $fromEnv, 'source' => 'environment'];
		}

		return ['value' => $default, 'source' => 'default'];
	}

	/**
	 * @param list<string> $envKeys
	 */
	private function envValue(array $envKeys): ?string {
		foreach ($envKeys as $envKey) {
			$fromEnv = getenv($envKey);
			if (is_string($fromEnv) && trim($fromEnv) !== '') {
				return trim($fromEnv);
			}

			$fromServer = $_SERVER[$envKey] ?? null;
			if (is_string($fromServer) && trim($fromServer) !== '') {
				return trim($fromServer);
			}
		}

		return null;
	}

	/**
	 * @param array<array-key, mixed> $decoded
	 * @psalm-suppress MixedAssignment
	 */
	private function extractPromptText(array $decoded): ?string {
		foreach (['text', 'prompt'] as $key) {
			$value = $decoded[$key] ?? null;
			if (is_string($value)) {
				return $value;
			}
			if (is_array($value)) {
				$parts = [];
				foreach ($value as $message) {
					if (is_array($message) && isset($message['content']) && is_string($message['content'])) {
						$parts[] = $message['content'];
					} elseif (is_string($message)) {
						$parts[] = $message;
					}
				}
				if ($parts !== []) {
					return implode("\n\n", $parts);
				}
			}
		}

		return null;
	}

	private function truncate(string $value): string {
		if (mb_strlen($value) <= 20000) {
			return $value;
		}

		return mb_substr($value, 0, 20000) . "\n[truncated]";
	}
}
