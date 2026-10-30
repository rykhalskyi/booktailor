<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Service\Ai\LangfuseService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LangfuseServiceTest extends TestCase {
	private const ENV_KEYS = [
		LangfuseService::ENV_URL,
		LangfuseService::ENV_PUBLIC_KEY,
		LangfuseService::ENV_SECRET_KEY,
		LangfuseService::ENV_PROMPT_LABEL,
		'LANGFUSE_HOST',
		'LANGFUSE_URL',
		'LANGFUSE_PUBLIC_KEY',
		'LANGFUSE_SECRET_KEY',
		'LANGFUSE_PROMPT_LABEL',
	];

	protected function setUp(): void {
		parent::setUp();
		$this->clearEnvironment();
	}

	protected function tearDown(): void {
		$this->clearEnvironment();
		parent::tearDown();
	}

	private function clearEnvironment(): void {
		foreach (self::ENV_KEYS as $key) {
			putenv($key);
			unset($_SERVER[$key], $_ENV[$key]);
		}
	}

	public function testMissingConfigurationDisablesTracingAndPrompts(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturn('');

		$service = new LangfuseService(
			$this->createMock(IClientService::class),
			$config,
			$this->createMock(LoggerInterface::class),
		);

		$this->assertFalse($service->isConfigured());
		$this->assertNull($service->fetchPrompt('booktailor-chat'));
		$this->assertNull($service->traceGeneration('user', 'trace', 'model', 'in', 'out'));
	}

	public function testEnvironmentVariablesWorkWithoutOccConfiguration(): void {
		putenv(LangfuseService::ENV_URL . '=http://langfuse.test');
		putenv(LangfuseService::ENV_PUBLIC_KEY . '=public');
		putenv(LangfuseService::ENV_SECRET_KEY . '=secret');
		try {
			$config = $this->createMock(IConfig::class);
			$config->method('getAppValue')->willReturn('');

			$service = new LangfuseService(
				$this->createMock(IClientService::class),
				$config,
				$this->createMock(LoggerInterface::class),
			);

			$this->assertTrue($service->isConfigured());
			$this->assertSame('http://langfuse.test', $service->baseUrl());
		} finally {
			putenv(LangfuseService::ENV_URL);
			putenv(LangfuseService::ENV_PUBLIC_KEY);
			putenv(LangfuseService::ENV_SECRET_KEY);
		}
	}

	public function testStandardLangfuseEnvAliasesWork(): void {
		putenv('LANGFUSE_HOST=http://langfuse.test');
		putenv('LANGFUSE_PUBLIC_KEY=public');
		putenv('LANGFUSE_SECRET_KEY=secret');
		try {
			$config = $this->createMock(IConfig::class);
			$config->method('getAppValue')->willReturn('');

			$service = new LangfuseService(
				$this->createMock(IClientService::class),
				$config,
				$this->createMock(LoggerInterface::class),
			);

			$this->assertTrue($service->isConfigured());
			$this->assertSame('http://langfuse.test', $service->baseUrl());
		} finally {
			putenv('LANGFUSE_HOST');
			putenv('LANGFUSE_PUBLIC_KEY');
			putenv('LANGFUSE_SECRET_KEY');
		}
	}

	public function testTracePayloadContainsRootSpanAndChildGeneration(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$captured = null;
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('post')
			->willReturnCallback(static function (string $url, array $options) use (&$captured, $response): IResponse {
				$captured = json_decode((string)$options['body'], true);

				return $response;
			});
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				LangfuseService::CONFIG_URL => 'http://langfuse.test',
				LangfuseService::CONFIG_PUBLIC_KEY => 'public',
				LangfuseService::CONFIG_SECRET_KEY => 'secret',
				default => $default,
			},
		);

		$service = new LangfuseService($clientService, $config, $this->createMock(LoggerInterface::class));
		$traceId = $service->traceGeneration('user', 'booktailor-chat', 'model', 'input', 'output', null, null, ['total' => 1], true);

		$this->assertNotNull($traceId);
		$this->assertIsArray($captured);
		$spans = $captured['resourceSpans'][0]['scopeSpans'][0]['spans'];
		$this->assertCount(2, $spans);
		$this->assertSame($traceId, $spans[0]['traceId']);
		$this->assertSame($spans[0]['spanId'], $spans[1]['parentSpanId']);
		$this->assertSame('span', $this->attribute($spans[0], 'langfuse.observation.type'));
		$this->assertSame('generation', $this->attribute($spans[1], 'langfuse.observation.type'));
		$this->assertSame('input', $this->attribute($spans[0], 'langfuse.observation.input'));
		$this->assertSame('output', $this->attribute($spans[0], 'langfuse.observation.output'));
		$this->assertSame('input', $this->attribute($spans[1], 'langfuse.observation.input'));
		$this->assertSame('output', $this->attribute($spans[1], 'langfuse.observation.output'));
	}

	public function testContentIsNotSentWhenLoggingIsDisabled(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$captured = null;
		$client = $this->createMock(IClient::class);
		$client->expects($this->once())
			->method('post')
			->willReturnCallback(static function (string $url, array $options) use (&$captured, $response): IResponse {
				$captured = json_decode((string)$options['body'], true);

				return $response;
			});
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				LangfuseService::CONFIG_URL => 'http://langfuse.test',
				LangfuseService::CONFIG_PUBLIC_KEY => 'public',
				LangfuseService::CONFIG_SECRET_KEY => 'secret',
				default => $default,
			},
		);

		$service = new LangfuseService($clientService, $config, $this->createMock(LoggerInterface::class));
		$service->traceGeneration('user', 'booktailor-chat', 'model', 'input', 'output');

		$this->assertIsArray($captured);
		$spans = $captured['resourceSpans'][0]['scopeSpans'][0]['spans'];
		$this->assertNull($this->attribute($spans[0], 'langfuse.observation.input'));
		$this->assertNull($this->attribute($spans[0], 'langfuse.observation.output'));
		$this->assertNull($this->attribute($spans[1], 'langfuse.observation.input'));
		$this->assertNull($this->attribute($spans[1], 'langfuse.observation.output'));
	}

	public function testTimestampsAreUsedForTheTraceAndGeneration(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(200);
		$captured = null;
		$client = $this->createMock(IClient::class);
		$client->method('post')
			->willReturnCallback(static function (string $url, array $options) use (&$captured, $response): IResponse {
				$captured = json_decode((string)$options['body'], true);

				return $response;
			});
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($client);

		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				LangfuseService::CONFIG_URL => 'http://langfuse.test',
				LangfuseService::CONFIG_PUBLIC_KEY => 'public',
				LangfuseService::CONFIG_SECRET_KEY => 'secret',
				default => $default,
			},
		);

		$service = new LangfuseService($clientService, $config, $this->createMock(LoggerInterface::class));
		$service->traceGeneration(
			'user',
			'booktailor-chat',
			'model',
			'input',
			'output',
			null,
			null,
			[],
			false,
			1000000000,
			2500000000,
		);

		$this->assertIsArray($captured);
		$spans = $captured['resourceSpans'][0]['scopeSpans'][0]['spans'];
		$this->assertSame('1000000000', $spans[0]['startTimeUnixNano']);
		$this->assertSame('2500000000', $spans[0]['endTimeUnixNano']);
		$this->assertSame('1000000000', $spans[1]['startTimeUnixNano']);
		$this->assertSame('2500000000', $spans[1]['endTimeUnixNano']);
	}

	/**
	 * @param array<string, mixed> $span
	 */
	private function attribute(array $span, string $key): ?string {
		foreach ($span['attributes'] as $attribute) {
			if (($attribute['key'] ?? null) === $key) {
				$value = $attribute['value'] ?? [];

				return $value['stringValue'] ?? $value['intValue'] ?? null;
			}
		}

		return null;
	}

	public function testLangfuseOutageFallsBackWithoutThrowing(): void {
		$config = $this->createMock(IConfig::class);
		$config->method('getAppValue')->willReturnCallback(
			static fn (string $app, string $key, string $default = ''): string => match ($key) {
				LangfuseService::CONFIG_URL => 'http://langfuse.test',
				LangfuseService::CONFIG_PUBLIC_KEY => 'public',
				LangfuseService::CONFIG_SECRET_KEY => 'secret',
				default => $default,
			},
		);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willThrowException(new \RuntimeException('offline'));

		$service = new LangfuseService(
			$clientService,
			$config,
			$this->createMock(LoggerInterface::class),
		);

		$this->assertTrue($service->isConfigured());
		$this->assertNull($service->fetchPrompt('booktailor-chat'));
		$this->assertNull($service->traceGeneration('user', 'trace', 'model', 'in', 'out'));
	}
}
