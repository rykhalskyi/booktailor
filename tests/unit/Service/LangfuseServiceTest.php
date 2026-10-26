<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Service\Ai\LangfuseService;
use OCP\Http\Client\IClientService;
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
