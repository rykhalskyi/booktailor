<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Service\Ai\AiProviderException;
use OCA\BookTailor\Service\Ai\OpenAiCompatibleProvider;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OpenAiCompatibleProviderTest extends TestCase {
	public function testCompleteUsesOpenAiCompatibleRequestAndReturnsContent(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn((string)json_encode([
			'model' => 'deepseek-chat',
			'choices' => [['message' => ['content' => '{"reply":"ok"}'], 'finish_reason' => 'stop']],
			'usage' => ['total_tokens' => 5],
		]));

		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('post')
			->with(
				'https://api.deepseek.com/chat/completions',
				$this->callback(static function (array $options): bool {
					$body = json_decode((string)$options['body'], true);

					return $options['headers']['Authorization'] === 'Bearer sk-secret'
						&& $body['model'] === 'deepseek-chat'
						&& $body['response_format']['type'] === 'json_object'
						&& $body['messages'][0]['role'] === 'user';
				}),
			)
			->willReturn($response);

		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$provider = new OpenAiCompatibleProvider($clientService, $this->createMock(LoggerInterface::class));
		$completion = $provider->complete('sk-secret', 'deepseek-chat', [
			['role' => 'user', 'content' => 'Hi'],
		], ['provider' => 'deepseek']);

		$this->assertSame('{"reply":"ok"}', $completion->content);
		$this->assertSame('deepseek-chat', $completion->model);
		$this->assertSame(['total_tokens' => 5], $completion->usage);
		$this->assertSame('stop', $completion->finishReason);
	}

	public function testEmptyContentIsReturnedWithFinishReason(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$response->method('getBody')->willReturn((string)json_encode([
			'model' => 'deepseek-reasoner',
			'choices' => [['message' => ['content' => '', 'reasoning_content' => 'Thinking...'], 'finish_reason' => 'length']],
			'usage' => ['total_tokens' => 5],
		]));

		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($response);

		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$provider = new OpenAiCompatibleProvider($clientService, $this->createMock(LoggerInterface::class));

		$completion = $provider->complete('sk-secret', 'deepseek-reasoner', [
			['role' => 'user', 'content' => 'Hi'],
		], ['provider' => 'deepseek']);

		$this->assertSame('', $completion->content);
		$this->assertSame('length', $completion->finishReason);
	}

	public function testProviderErrorDoesNotLeakTheKey(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(401);
		$response->method('getBody')->willReturn('{"error":"unauthorized"}');

		$client = $this->createMock(IClient::class);
		$client->method('post')->willReturn($response);

		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);
		$provider = new OpenAiCompatibleProvider($clientService, $this->createMock(LoggerInterface::class));

		try {
			$provider->complete('sk-secret', 'deepseek-chat', [['role' => 'user', 'content' => 'Hi']], ['provider' => 'deepseek']);
			$this->fail('Expected the provider to reject the request');
		} catch (AiProviderException $e) {
			$this->assertStringNotContainsString('sk-secret', $e->getMessage());
		}
	}

	public function testUnsupportedProviderIsRejected(): void {
		$provider = new OpenAiCompatibleProvider(
			$this->createMock(IClientService::class),
			$this->createMock(LoggerInterface::class),
		);

		$this->expectException(AiProviderException::class);
		$provider->complete('key', 'model', [], ['provider' => 'unknown']);
	}
}
