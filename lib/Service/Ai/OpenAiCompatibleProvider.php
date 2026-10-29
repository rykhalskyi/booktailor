<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class OpenAiCompatibleProvider implements AiProviderInterface {
	private const ENDPOINTS = [
		'deepseek' => 'https://api.deepseek.com/chat/completions',
		'siliconflow' => 'https://api.siliconflow.com/v1/chat/completions',
	];

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IClientService $clientService,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<array{role: string, content: string}> $messages
	 * @param array<string, mixed> $options
	 */
	public function complete(
		string $apiKey,
		string $model,
		array $messages,
		array $options = [],
	): AiCompletion {
		$provider = (string)($options['provider'] ?? '');
		$url = self::ENDPOINTS[$provider] ?? null;
		if ($url === null) {
			throw new AiProviderException('Unsupported AI provider');
		}

		$body = [
			'model' => $model,
			'messages' => $messages,
			'max_tokens' => max(1, (int)($options['maxTokens'] ?? 2048)),
			'temperature' => (float)($options['temperature'] ?? 0.2),
		];
		if (($options['json'] ?? true) === true) {
			$body['response_format'] = ['type' => 'json_object'];
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $apiKey,
					'Content-Type' => 'application/json',
				],
				'body' => (string)json_encode($body, JSON_UNESCAPED_SLASHES),
				'connect_timeout' => max(1, (int)($options['connectTimeout'] ?? 30)),
				'timeout' => max(1, (int)($options['readTimeout'] ?? 60)),
			]);

			$status = $response->getStatusCode();
			if ($status < 200 || $status >= 300) {
				$this->logger->warning('BookTailor AI provider returned an error', [
					'provider' => $provider,
					'status' => $status,
				]);
				throw new AiProviderException('The AI provider rejected the request. Check the API key and model in Settings.');
			}

			$decoded = json_decode((string)$response->getBody(), true);
			if (!is_array($decoded)) {
				throw new AiProviderException('The AI provider returned an invalid response');
			}

			/** @var array{choices?: list<array{message?: array{content?: string, reasoning_content?: string}, finish_reason?: string}>, model?: string, usage?: array<string, mixed>} $decoded */
			$choice = $decoded['choices'][0] ?? [];
			$message = $choice['message'] ?? [];
			$content = $message['content'] ?? null;
			$content = is_string($content) ? $content : '';
			$reasoningContent = $message['reasoning_content'] ?? null;

			$finishReason = $choice['finish_reason'] ?? null;
			$resolvedModel = $decoded['model'] ?? $model;
			$usage = $decoded['usage'] ?? [];

			if (trim($content) === '') {
				$this->logger->warning('BookTailor AI provider returned an empty response', [
					'provider' => $provider,
					'model' => $resolvedModel,
					'finishReason' => $finishReason,
					'hasReasoningContent' => is_string($reasoningContent) && trim($reasoningContent) !== '',
				]);
			}

			return new AiCompletion(
				$content,
				$resolvedModel,
				$usage,
				is_string($finishReason) ? $finishReason : null,
			);
		} catch (AiProviderException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor AI provider request failed', [
				'provider' => $provider,
				'exceptionClass' => $e::class,
			]);
			throw new AiProviderException('Could not reach the AI provider. Check the network and the active profile.', 0, $e);
		}
	}
}
