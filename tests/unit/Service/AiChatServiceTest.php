<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Entity\AiAction;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\LlmProfile;
use OCA\BookTailor\Service\Ai\AiChatService;
use OCA\BookTailor\Service\Ai\AiCompletion;
use OCA\BookTailor\Service\Ai\AiContextService;
use OCA\BookTailor\Service\Ai\AiPromptService;
use OCA\BookTailor\Service\Ai\AiProposalService;
use OCA\BookTailor\Service\Ai\AiProviderInterface;
use OCA\BookTailor\Service\Ai\AiProviderRegistry;
use OCA\BookTailor\Service\Ai\AiSettingsService;
use OCA\BookTailor\Service\Ai\AiToolDispatcher;
use OCA\BookTailor\Service\Ai\LangfuseService;
use OCA\BookTailor\Service\Ai\PromptCatalog;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\LlmProfileService;
use PHPUnit\Framework\TestCase;

final class AiChatServiceTest extends TestCase {
	/**
	 * @param array<string, mixed> $payload
	 */
	private function service(AiProviderInterface $provider, array $payload = [], ?LangfuseService $langfuse = null): AiChatService {
		$books = $this->createMock(BookService::class);
		$books->method('get')->willReturn($this->createMock(Book::class));

		$profiles = $this->createMock(LlmProfileService::class);
		$profile = new LlmProfile();
		$profile->setId(5);
		$profile->setProvider('deepseek');
		$profile->setModel('deepseek-chat');
		$profile->setMaxTokens(2048);
		$profile->setConnectTimeout(30);
		$profile->setReadTimeout(60);
		$profiles->method('activeCredentials')->willReturn(['profile' => $profile, 'apiKey' => 'secret-key']);

		$registry = $this->createMock(AiProviderRegistry::class);
		$registry->method('provider')->willReturn($provider);

		$catalog = $this->createMock(PromptCatalog::class);
		$catalog->method('has')->willReturn(true);
		$catalog->method('langfuseName')->willReturn('booktailor-chat');
		$catalog->method('systemPrompt')->willReturn('You are a test assistant.');

		$langfuse ??= $this->createMock(LangfuseService::class);
		$langfuse->method('fetchPrompt')->willReturn(null);
		$langfuse->method('traceGeneration')->willReturn('trace-1');

		$settings = $this->createMock(AiSettingsService::class);
		$settings->method('contentLogging')->willReturn(false);

		$context = $this->createMock(AiContextService::class);
		$context->method('build')->willReturn(['text' => 'context', 'assetIds' => [], 'chapterIds' => []]);

		$tools = $this->createMock(AiToolDispatcher::class);
		$tools->method('describe')->willReturn('get_book');

		$action = new AiAction();
		$action->setId(12);
		$action->setUserId('user');
		$action->setBookId(1);
		$action->setAction('create_asset');
		$action->setPayload((string)json_encode($payload));
		$action->setStatus(AiAction::STATUS_PROPOSED);
		$action->setCreatedAt(new \DateTime());

		$proposals = $this->createMock(AiProposalService::class);
		$proposals->method('describeActions')->willReturn('create_asset');
		$proposals->method('propose')->willReturn($action);
		$proposals->method('serialize')->willReturn([
			'id' => 12,
			'userId' => 'user',
			'bookId' => 1,
			'action' => 'create_asset',
			'payload' => $payload,
			'status' => 'proposed',
			'createdAt' => '2026-01-01T00:00:00+00:00',
			'decidedAt' => null,
			'result' => null,
		]);

		return new AiChatService(
			$books,
			$profiles,
			$registry,
			$catalog,
			$langfuse,
			$settings,
			$context,
			$tools,
			$proposals,
			$this->createMock(AiPromptService::class),
		);
	}

	public function testChatReturnsReplyProposalsAndTrace(): void {
		$provider = new class implements AiProviderInterface {
			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				return new AiCompletion(
					'{"reply":"I prepared a summary.","toolCalls":[],"proposals":[{"action":"create_asset","payload":{"chapterId":2,"name":"Summary","content":"# Summary"},"summary":"Create summary"}]}',
					$model,
					['total_tokens' => 10],
				);
			}
		};

		$result = $this->service($provider, ['chapterId' => 2, 'name' => 'Summary', 'content' => '# Summary'])
			->chat('user', 1, 'Summarize this chapter');

		$this->assertSame('I prepared a summary.', $result['message']);
		$this->assertSame('trace-1', $result['traceId']);
		$this->assertCount(1, $result['proposedActions']);
		$this->assertSame('create_asset', $result['proposedActions'][0]['action']);
	}

	public function testTruncatedResponseIsRetriedWithMoreTokens(): void {
		$provider = new class implements AiProviderInterface {
			/** @var list<int> */
			public array $maxTokens = [];
			private int $calls = 0;

			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				$this->maxTokens[] = (int)($options['maxTokens'] ?? 0);
				$this->calls++;
				if ($this->calls === 1) {
					return new AiCompletion('{"reply":"cut off', $model, [], 'length');
				}

				return new AiCompletion('{"reply":"Recovered.","toolCalls":[],"proposals":[]}', $model, [], 'stop');
			}
		};

		$result = $this->service($provider)->chat('user', 1, 'Build a glossary');

		$this->assertSame([2048, 4096], $provider->maxTokens);
		$this->assertSame('Recovered.', $result['message']);
		$this->assertSame([], $result['warnings']);
	}

	public function testTruncatedResponseReportsAnActionableWarning(): void {
		$provider = new class implements AiProviderInterface {
			/** @var list<int> */
			public array $maxTokens = [];

			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				$this->maxTokens[] = (int)($options['maxTokens'] ?? 0);

				return new AiCompletion('{"reply":"cut off', $model, [], 'length');
			}
		};

		$result = $this->service($provider)->chat('user', 1, 'Build a glossary');

		$this->assertSame([2048, 4096, 8192, 16384], $provider->maxTokens);
		$this->assertStringContainsString('Max tokens', $result['message']);
		$this->assertCount(1, $result['warnings']);
	}

	public function testHistoryIsSentBeforeTheCurrentUserMessage(): void {
		$provider = new class implements AiProviderInterface {
			/** @var list<array{role: string, content: string}> */
			public array $messages = [];

			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				$this->messages = $messages;

				return new AiCompletion('{"reply":"ok","toolCalls":[],"proposals":[]}', $model, []);
			}
		};

		$this->service($provider)->chat('user', 1, 'The one on fractions', null, null, [], null, null, [
			['role' => 'user', 'content' => 'Build a glossary from chapter 1.2'],
			['role' => 'assistant', 'content' => 'Which chapter did you mean?'],
			['role' => 'system', 'content' => 'ignored'],
			['role' => 'user', 'content' => 'The one on fractions'],
		]);

		$this->assertSame(['system', 'user', 'assistant', 'user'], array_column($provider->messages, 'role'));
		$this->assertSame('Build a glossary from chapter 1.2', $provider->messages[1]['content']);
		$this->assertSame('Which chapter did you mean?', $provider->messages[2]['content']);
		$this->assertSame('The one on fractions', $provider->messages[3]['content']);
	}

	public function testTraceInputContainsTheConversationHistory(): void {
		$provider = new class implements AiProviderInterface {
			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				return new AiCompletion('{"reply":"ok","toolCalls":[],"proposals":[]}', $model, []);
			}
		};
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->method('traceGeneration')
			->with(
				'user',
				'booktailor-chat',
				'deepseek-chat',
				$this->callback(static function (string $input): bool {
					return str_contains($input, 'Which chapter did you mean?')
						&& str_contains($input, 'The one on fractions');
				}),
				'{"reply":"ok","toolCalls":[],"proposals":[]}',
				null,
				null,
				[],
				false,
			)
			->willReturn('trace-3');

		$result = $this->service($provider, [], $langfuse)->chat('user', 1, 'The one on fractions', null, null, [], null, null, [
			['role' => 'assistant', 'content' => 'Which chapter did you mean?'],
		]);

		$this->assertSame('trace-3', $result['traceId']);
	}

	public function testTraceInputContainsTheSelectedPromptAndUserMessage(): void {
		$provider = new class implements AiProviderInterface {
			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				return new AiCompletion('{"reply":"ok","toolCalls":[],"proposals":[]}', $model, []);
			}
		};
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->method('traceGeneration')
			->with(
				'user',
				'booktailor-chat',
				'deepseek-chat',
				$this->callback(static function (string $input): bool {
					return str_contains($input, 'You are a test assistant.')
						&& str_contains($input, 'Hello from the user')
						&& str_contains($input, 'context');
				}),
				'{"reply":"ok","toolCalls":[],"proposals":[]}',
				null,
				null,
				[],
				false,
			)
			->willReturn('trace-2');

		$result = $this->service($provider, [], $langfuse)->chat('user', 1, 'Hello from the user');

		$this->assertSame('trace-2', $result['traceId']);
	}

	public function testNonJsonProviderResponseFallsBackToPlainText(): void {
		$provider = new class implements AiProviderInterface {
			public function complete(string $apiKey, string $model, array $messages, array $options = []): AiCompletion {
				return new AiCompletion('Plain answer without JSON', $model, []);
			}
		};

		$result = $this->service($provider)->chat('user', 1, 'Hello');

		$this->assertSame('Plain answer without JSON', $result['message']);
		$this->assertSame([], $result['proposedActions']);
	}
}
