<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCA\BookTailor\Exception\ApiException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\LlmProfileService;

class AiChatService {
	private const MAX_MESSAGE_CHARS = 8000;
	private const MAX_TOOL_ROUNDS = 3;
	private const MAX_TOOL_CALLS = 8;
	private const MAX_PROPOSALS = 5;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookService $bookService,
		private readonly LlmProfileService $profileService,
		private readonly AiProviderRegistry $providerRegistry,
		private readonly PromptCatalog $promptCatalog,
		private readonly LangfuseService $langfuseService,
		private readonly AiSettingsService $aiSettingsService,
		private readonly AiContextService $contextService,
		private readonly AiToolDispatcher $toolDispatcher,
		private readonly AiProposalService $proposalService,
		private readonly AiPromptService $promptService,
	) {
	}

	/**
	 * @param list<int> $contextAssetIds
	 * @return array{message: string, proposedActions: list<array<string, mixed>>, traceId: ?string, warnings: list<string>, usage: array<string, mixed>}
	 */
	public function chat(
		string $userId,
		int $bookId,
		string $message,
		?int $structureId = null,
		?int $chapterId = null,
		array $contextAssetIds = [],
		?string $promptKey = null,
		?int $promptId = null,
	): array {
		$this->bookService->get($userId, $bookId);
		$message = trim($message);
		if ($message === '') {
			throw new ValidationException('A message is required');
		}
		if (mb_strlen($message) > self::MAX_MESSAGE_CHARS) {
			throw new ValidationException('The message is too long');
		}

		$credentials = $this->profileService->activeCredentials($userId);
		$profile = $credentials['profile'];

		[$systemPrompt, $promptName, $promptVersion, $traceName] = $this->resolvePrompt($userId, $bookId, $promptKey, $promptId);
		$context = $this->contextService->build(
			$userId,
			$bookId,
			$structureId,
			$chapterId,
			$contextAssetIds,
		);

		$messages = [
			[
				'role' => 'system',
				'content' => $systemPrompt . "\n\n" . $this->protocolInstructions() . "\n\n## Book context\n\n" . $context['text'],
			],
			['role' => 'user', 'content' => $message],
		];

		$provider = $this->providerRegistry->provider($profile->getProvider());
		$model = $profile->getModel();
		if ($model === null || trim($model) === '') {
			$model = AiProviderCatalog::defaultModel($profile->getProvider());
		}

		$options = [
			'provider' => $profile->getProvider(),
			'maxTokens' => $profile->getMaxTokens(),
			'connectTimeout' => $profile->getConnectTimeout(),
			'readTimeout' => $profile->getReadTimeout(),
			'json' => true,
		];

		$reply = '';
		$proposals = [];
		$warnings = [];
		$usage = [];
		$lastOutput = '';

		try {
			for ($round = 0; $round <= self::MAX_TOOL_ROUNDS; $round++) {
				$completion = $provider->complete($credentials['apiKey'], $model, $messages, $options);
				$lastOutput = $completion->content;
				$usage = $completion->usage;
				$parsed = $this->parseJsonObject($completion->content);

				if ($parsed === null) {
					$reply = trim($completion->content);
					break;
				}

				/** @psalm-suppress MixedAssignment */
				$replyValue = $parsed['reply'] ?? null;
				$reply = is_string($replyValue) ? trim($replyValue) : '';
				$toolCalls = $this->normalizeToolCalls($parsed['toolCalls'] ?? []);
				$rawProposals = $this->normalizeProposals($parsed['proposals'] ?? []);

				if ($toolCalls !== [] && $round < self::MAX_TOOL_ROUNDS) {
					$toolResults = [];
					foreach ($toolCalls as $call) {
						try {
							$toolResults[] = [
								'name' => $call['name'],
								'result' => $this->toolDispatcher->read($userId, $bookId, $call['name'], $call['arguments']),
							];
						} catch (ApiException $e) {
							$toolResults[] = ['name' => $call['name'], 'error' => $e->getMessage()];
						}
					}

					$messages[] = ['role' => 'assistant', 'content' => $completion->content];
					$messages[] = [
						'role' => 'user',
						'content' => "Tool results (JSON):\n"
							. (string)json_encode($toolResults, JSON_UNESCAPED_SLASHES)
							. "\n\nContinue and return the final JSON object. Do not call more tools unless absolutely necessary.",
					];
					continue;
				}

				foreach ($rawProposals as $proposal) {
					try {
						$stored = $this->proposalService->propose(
							$userId,
							$bookId,
							$proposal['action'],
							$proposal['payload'],
						);
						$proposals[] = $this->proposalService->serialize($stored);
					} catch (ApiException $e) {
						$warnings[] = 'A proposed action was rejected by validation: ' . $e->getMessage();
					}
				}
				break;
			}
		} catch (AiProviderException $e) {
			throw new ApiException($e->getMessage(), 502);
		}

		if ($reply === '' && $proposals !== []) {
			$reply = 'I prepared the following changes for your confirmation.';
		}
		if ($reply === '') {
			$reply = 'I could not produce a response. Please try again.';
		}

		$traceInput = "## Selected prompt\n\n" . $systemPrompt
			. "\n\n## User message\n\n" . $message
			. "\n\n## Book context\n\n" . $context['text'];
		$traceId = $this->langfuseService->traceGeneration(
			$userId,
			$traceName,
			$model,
			$traceInput,
			$lastOutput !== '' ? $lastOutput : $reply,
			$promptName,
			$promptVersion,
			$usage,
			$this->aiSettingsService->contentLogging($userId),
		);

		return [
			'message' => $reply,
			'proposedActions' => $proposals,
			'traceId' => $traceId,
			'warnings' => $warnings,
			'usage' => $usage,
		];
	}

	/**
	 * @return array{0: string, 1: ?string, 2: ?int, 3: string}
	 */
	private function resolvePrompt(string $userId, int $bookId, ?string $promptKey, ?int $promptId): array {
		if ($promptId !== null) {
			$prompt = $this->promptService->getOwned($userId, $promptId);
			if ($prompt->getBookId() !== $bookId) {
				throw new ValidationException('The selected prompt belongs to another book');
			}

			return [$prompt->getText(), null, null, 'booktailor-saved-prompt'];
		}

		$key = $promptKey !== null && trim($promptKey) !== '' ? trim($promptKey) : PromptCatalog::KEY_CHAT;
		if (!$this->promptCatalog->has($key)) {
			throw new ValidationException('Unknown AI prompt');
		}

		$name = $this->promptCatalog->langfuseName($key);
		$fetched = $this->langfuseService->fetchPrompt($name);
		if ($fetched !== null) {
			return [$fetched['text'], $name, $fetched['version'], $name];
		}

		return [$this->promptCatalog->systemPrompt($key), null, null, 'booktailor-' . $key];
	}

	private function protocolInstructions(): string {
		return <<<PROMPT
You must reply with one JSON object only. Do not wrap it in Markdown fences.

Schema:
{
  "reply": "Markdown text for the user",
  "toolCalls": [{"name": "tool_name", "arguments": {}}],
  "proposals": [{"action": "action_name", "payload": {}, "summary": "short preview"}]
}

Rules:
- Use toolCalls only when you need more book context. Never invent content that a tool could provide.
- Allowed read tools: {$this->toolDispatcher->describe()}.
- Allowed proposal actions: {$this->proposalService->describeActions()}.
- A proposal is not applied until the user confirms it. Never say that a change has been made.
- For create_asset use payload {chapterId, name, content, optional tagIds}.
- For update_asset use payload {assetId, optional name, optional content}.
- For delete_asset use payload {assetId}.
- For create_chapter use payload {name, optional parentId}.
- For rename_chapter use payload {chapterId, name}.
- For delete_chapter use payload {chapterId}.
- For create_structure use payload {name}.
- For rename_structure use payload {structureId, name}.
- For delete_structure use payload {structureId}.
- Never include API keys or secrets in any output.
- Keep reply concise. Put full generated Markdown only inside a proposal payload.
PROMPT;
	}

	/**
	 * @return array<string, mixed>|null
	 * @psalm-suppress MixedAssignment, MixedReturnTypeCoercion
	 */
	private function parseJsonObject(string $content): ?array {
		$clean = trim($content);
		$clean = (string)preg_replace('/^```(?:json)?\s*/i', '', $clean);
		$clean = (string)preg_replace('/\s*```$/', '', $clean);
		$decoded = json_decode($clean, true);
		if (is_array($decoded)) {
			/** @var array<string, mixed> $decoded */
			return $decoded;
		}

		$start = strpos($clean, '{');
		$end = strrpos($clean, '}');
		if ($start === false || $end === false || $end <= $start) {
			return null;
		}

		$decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
		if (!is_array($decoded)) {
			return null;
		}

		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	/**
	 * @param mixed $value
	 * @return list<array{name: string, arguments: array<string, mixed>}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedReturnTypeCoercion, RedundantCast
	 */
	private function normalizeToolCalls(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}

		$calls = [];
		foreach (array_slice($value, 0, self::MAX_TOOL_CALLS) as $call) {
			if (!is_array($call) || !is_string($call['name'] ?? null) || trim($call['name']) === '') {
				continue;
			}
			$arguments = $call['arguments'] ?? [];
			if (is_string($arguments)) {
				$decoded = json_decode($arguments, true);
				$arguments = is_array($decoded) ? $decoded : [];
			}
			if (!is_array($arguments)) {
				$arguments = [];
			}
			$calls[] = ['name' => trim($call['name']), 'arguments' => $arguments];
		}

		return $calls;
	}

	/**
	 * @param mixed $value
	 * @return list<array{action: string, payload: array<string, mixed>}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedReturnTypeCoercion
	 */
	private function normalizeProposals(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}

		$proposals = [];
		foreach (array_slice($value, 0, self::MAX_PROPOSALS) as $proposal) {
			if (!is_array($proposal) || !is_string($proposal['action'] ?? null)) {
				continue;
			}
			$payload = $proposal['payload'] ?? [];
			if (!is_array($payload)) {
				continue;
			}
			if (is_string($proposal['summary'] ?? null) && !isset($payload['summary'])) {
				$payload['summary'] = $proposal['summary'];
			}
			$proposals[] = [
				'action' => trim($proposal['action']),
				'payload' => $payload,
			];
		}

		return $proposals;
	}
}
