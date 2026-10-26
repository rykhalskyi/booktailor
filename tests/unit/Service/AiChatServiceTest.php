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
	private function service(AiProviderInterface $provider, array $payload = []): AiChatService {
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

		$langfuse = $this->createMock(LangfuseService::class);
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
