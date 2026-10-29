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
	private const MAX_HISTORY_MESSAGES = 20;
	private const MAX_HISTORY_CHARS = 20000;
	private const MAX_OUTPUT_TOKENS = 16384;
	private const MAX_COMPLETION_ATTEMPTS = 4;

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
	 * @param list<array{role: string, content: string}> $history
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
		array $history = [],
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

		$normalizedHistory = $this->normalizeHistory($history, $message);

		$messages = [
			[
				'role' => 'system',
				'content' => $systemPrompt . "\n\n" . $this->protocolInstructions() . "\n\n## Book context\n\n" . $context['text'],
			],
		];
		foreach ($normalizedHistory as $historyMessage) {
			$messages[] = [
				'role' => $historyMessage['role'],
				'content' => $historyMessage['content'],
			];
		}
		$messages[] = ['role' => 'user', 'content' => $message];

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
				$completion = $this->completeWithBudget($provider, $credentials['apiKey'], $model, $messages, $options);
				$lastOutput = $completion->content;
				$usage = $completion->usage;

				if (trim($completion->content) === '') {
					if ($completion->finishReason === 'length') {
						$reply = 'The AI response was too long and was cut off before it could answer. '
							. 'Increase "Max tokens" in the AI profile settings, or ask for a smaller batch.';
						$warnings[] = 'The model reached its output token limit.';
					} else {
						$reply = 'The AI provider returned an empty response. Please try again, '
							. 'or check the model and "Max tokens" in the AI profile settings.';
						$warnings[] = 'The AI provider returned an empty response.';
					}
					break;
				}

				$parsed = $this->parseJsonObject($completion->content);

				if ($parsed === null) {
					if ($completion->finishReason === 'length') {
						$reply = 'The AI response was too long and was cut off before it could create a proposal. '
							. 'Increase "Max tokens" in the AI profile settings, or ask for a smaller batch.';
						$warnings[] = 'The model reached its output token limit.';
					} else {
						$reply = trim($completion->content);
					}
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
								'result' => $this->toolDispatcher->read($userId, $bookId, $call['name'], $call['arguments'], $structureId),
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
			. ($normalizedHistory === [] ? '' : "\n\n## Conversation history\n\n" . $this->renderHistory($normalizedHistory))
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
	 * Retry a truncated completion with a larger output budget. Providers often
	 * cut JSON output at max_tokens, which makes the final object unparseable.
	 *
	 * @param array<string, mixed> $options
	 * @param list<array{role: string, content: string}> $messages
	 */
	private function completeWithBudget(
		AiProviderInterface $provider,
		string $apiKey,
		string $model,
		array $messages,
		array $options,
	): AiCompletion {
		$maxTokens = max(1, (int)($options['maxTokens'] ?? 2048));
		$ceiling = max(self::MAX_OUTPUT_TOKENS, $maxTokens);
		$completion = $provider->complete($apiKey, $model, $messages, $options);

		for ($attempt = 1; $attempt < self::MAX_COMPLETION_ATTEMPTS; $attempt++) {
			$isEmpty = trim($completion->content) === '';
			if ($completion->finishReason === 'length' && $maxTokens >= $ceiling) {
				return $completion;
			}
			if (!$isEmpty && $completion->finishReason !== 'length') {
				return $completion;
			}
			if ($completion->finishReason === 'length') {
				$maxTokens = min($ceiling, $maxTokens * 2);
			} elseif ($attempt > 1) {
				return $completion;
			}

			$options['maxTokens'] = $maxTokens;
			$completion = $provider->complete($apiKey, $model, $messages, $options);
		}

		return $completion;
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
- The chapter tree exposes each chapter's number, path, name, and id. A user may reference a chapter by number (1.2), name, or path.
- If a chapter or tag reference is ambiguous or required information is missing, ask the user in "reply" and return no toolCalls or proposals. Never guess.
- Do not include descendant chapters unless the user explicitly asks; use includeDescendants=true only in that case.
- Tags are free-form. Any tag the user names is valid if it exists in the book. If it does not exist, list the available tags and ask which one to use. Never assume a specific tag name.
- To gather assets across chapters and tags, call collect_assets with {chapterNumbers, chapterNames, chapterIds, tagNames, tagIds, includeDescendants, tagMode}. Use tagMode "any" unless the user requires assets with all listed tags.
- For merge/glossary requests: collect the source assets, inspect the target chapter with list_assets, and ask whether to update an existing matching asset or create a new one. Curate and deduplicate the content; do not concatenate raw content. Include source chapter references in the generated Markdown.
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
	 * @param array<array-key, mixed> $history
	 * @return list<array{role: 'user'|'assistant', content: string}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedReturnTypeCoercion
	 */
	private function normalizeHistory(array $history, string $currentMessage): array {
		$result = [];
		$chars = 0;
		$items = array_slice(array_values($history), -self::MAX_HISTORY_MESSAGES);

		foreach (array_reverse($items) as $item) {
			if (!is_array($item)) {
				continue;
			}
			$role = $item['role'] ?? null;
			$content = $item['content'] ?? null;
			if (!is_string($role) || !is_string($content)) {
				continue;
			}
			$role = mb_strtolower(trim($role));
			if (!in_array($role, ['user', 'assistant'], true)) {
				continue;
			}
			$content = trim($content);
			if ($content === '') {
				continue;
			}
			if ($result === [] && $role === 'user' && $content === $currentMessage) {
				continue;
			}
			$length = mb_strlen($content);
			if ($chars + $length > self::MAX_HISTORY_CHARS) {
				break;
			}
			array_unshift($result, ['role' => $role, 'content' => $content]);
			$chars += $length;
		}

		return $result;
	}

	/**
	 * @param list<array{role: 'user'|'assistant', content: string}> $history
	 */
	private function renderHistory(array $history): string {
		$parts = [];
		foreach ($history as $message) {
			$parts[] = '### ' . ucfirst($message['role']) . "\n\n" . $message['content'];
		}

		return implode("\n\n", $parts);
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
			$payload = $proposal['payload'] ?? $proposal['arguments'] ?? [];
			if (is_string($payload)) {
				$decoded = json_decode($payload, true);
				$payload = is_array($decoded) ? $decoded : [];
			}
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
