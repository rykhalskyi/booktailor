<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Exception\ApiException;
use OCA\BookTailor\Service\Ai\AiSettingsService;
use OCA\BookTailor\Service\Ai\LangfuseService;
use OCP\IConfig;
use PHPUnit\Framework\TestCase;

final class AiSettingsServiceTest extends TestCase {
	/**
	 * @param array<string, string> $store
	 */
	private function config(array &$store): IConfig {
		$config = $this->createMock(IConfig::class);
		$config->method('setAppValue')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$store): void {
				$store[$key] = $value;
			},
		);
		$config->method('deleteAppValue')->willReturnCallback(
			static function (string $app, string $key) use (&$store): void {
				unset($store[$key]);
			},
		);

		return $config;
	}

	public function testUpdateStoresValuesAndOmitsUnchangedFields(): void {
		$store = [];
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->expects($this->once())->method('storeSecret')->with('sk-test');
		$service = new AiSettingsService($this->config($store), $langfuse);

		$service->updateLangfuseSettings('https://langfuse.test', 'pk-test', 'sk-test', 'production');

		$this->assertSame([
			LangfuseService::CONFIG_URL => 'https://langfuse.test',
			LangfuseService::CONFIG_PUBLIC_KEY => 'pk-test',
			LangfuseService::CONFIG_PROMPT_LABEL => 'production',
		], $store);

		$service->updateLangfuseSettings(null, 'pk-updated');

		$this->assertSame('pk-updated', $store[LangfuseService::CONFIG_PUBLIC_KEY]);
		$this->assertArrayNotHasKey(LangfuseService::CONFIG_SECRET_KEY, $store);
	}

	public function testEmptyValuesClearStoredConfiguration(): void {
		$store = [
			LangfuseService::CONFIG_URL => 'https://langfuse.test',
			LangfuseService::CONFIG_PUBLIC_KEY => 'pk-test',
			LangfuseService::CONFIG_PROMPT_LABEL => 'production',
		];
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->expects($this->once())->method('storeSecret')->with('');
		$service = new AiSettingsService($this->config($store), $langfuse);

		$service->updateLangfuseSettings('', '', '', '');

		$this->assertSame([], $store);
	}

	public function testClearLangfuseSettingsRemovesStoredSecret(): void {
		$store = [
			LangfuseService::CONFIG_URL => 'https://langfuse.test',
			LangfuseService::CONFIG_PUBLIC_KEY => 'pk-test',
			LangfuseService::CONFIG_PROMPT_LABEL => 'production',
		];
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->expects($this->once())->method('clearSecret');
		$service = new AiSettingsService($this->config($store), $langfuse);

		$service->clearLangfuseSettings();

		$this->assertSame([], $store);
	}

	public function testInvalidUrlIsRejected(): void {
		$store = [];
		$service = new AiSettingsService($this->config($store), $this->createMock(LangfuseService::class));

		$this->expectException(ApiException::class);
		$service->updateLangfuseSettings('ftp://langfuse.test');
	}

	public function testInternalHostnamesWithUnderscoresAreAccepted(): void {
		$store = [];
		$service = new AiSettingsService($this->config($store), $this->createMock(LangfuseService::class));

		$service->updateLangfuseSettings('http://langfuse_lan:3000');

		$this->assertSame('http://langfuse_lan:3000', $store[LangfuseService::CONFIG_URL]);
	}

	public function testSettingsNeverReturnTheSecretKey(): void {
		$langfuse = $this->createMock(LangfuseService::class);
		$langfuse->method('baseUrl')->willReturn('https://langfuse.test');
		$langfuse->method('publicKey')->willReturn('pk-test');
		$langfuse->method('promptLabel')->willReturn('production');
		$langfuse->method('hasSecret')->willReturn(true);
		$langfuse->method('isConfigured')->willReturn(true);
		$langfuse->method('sources')->willReturn([
			'url' => 'app',
			'publicKey' => 'app',
			'secretKey' => 'app',
			'promptLabel' => 'none',
		]);

		$service = new AiSettingsService($this->createMock(IConfig::class), $langfuse);

		$settings = $service->langfuseSettings();
		$this->assertSame('https://langfuse.test', $settings['url']);
		$this->assertSame('pk-test', $settings['publicKey']);
		$this->assertTrue($settings['secretConfigured']);
		$this->assertSame('app', $settings['sources']['secretKey']);
		$this->assertArrayNotHasKey('secretKey', $settings);
	}
}
