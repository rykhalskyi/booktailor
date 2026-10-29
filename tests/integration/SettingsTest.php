<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Service\SettingsService;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function testSettingsDescribeStorageLimitsDefaultsAndVersions(): void {
		$settings = (new Application())->getContainer()->get(SettingsService::class)->get();

		$this->assertSame('BookTailor', $settings['storage']['root']);
		$this->assertGreaterThan(0, $settings['limits']['uploadBytes']);
		$this->assertGreaterThan(0, $settings['limits']['imageUploadBytes']);
		$this->assertLessThanOrEqual(10 * 1024 * 1024, $settings['limits']['imageUploadBytes']);
		$this->assertSame(
			['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
			$settings['limits']['imageTypes'],
		);
		$this->assertSame('text', $settings['defaults']['editor']);
		$this->assertSame('markdown', $settings['defaults']['compositionFormat']);
		$this->assertSame('json', $settings['defaults']['aiTransport']);
		$this->assertNotSame('', $settings['app']['version']);
		$this->assertNotSame('', $settings['app']['nextcloudVersion']);
		$this->assertArrayNotHasKey('apiKey', $settings);
	}
}
