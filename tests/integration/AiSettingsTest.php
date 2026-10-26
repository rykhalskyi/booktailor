<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Service\Ai\AiSettingsService;
use OCA\BookTailor\Service\LlmProfileService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class AiSettingsTest extends TestCase {
	private string $userId;
	private IUser $user;
	private LlmProfileService $profiles;
	private AiSettingsService $settings;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_llm_profiles')) {
			$this->markTestSkipped('BookTailor AI tables are not migrated');
		}

		$this->userId = 'bt-ai-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->profiles = $container->get(LlmProfileService::class);
		$this->settings = $container->get(AiSettingsService::class);
	}

	protected function tearDown(): void {
		try {
			foreach ($this->profiles->list($this->userId) as $profile) {
				$this->profiles->delete($this->userId, $profile->getId());
			}
		} catch (\Throwable) {
			// Best-effort cleanup.
		}

		$this->user->delete();
	}

	public function testProfileLifecycleKeepsKeysWriteOnly(): void {
		$first = $this->profiles->create(
			$this->userId,
			'DeepSeek',
			'deepseek',
			'sk-test-secret-1234',
			'deepseek-chat',
		);
		$this->assertTrue($first->getIsActive());
		$this->assertSame('sk-test-secret-1234', $this->profiles->decrypt($first));

		$serialized = $this->profiles->serialize($first);
		$this->assertSame('••••••••1234', $serialized['apiKeyMasked']);
		$this->assertArrayNotHasKey('apiKey', $serialized);
		$this->assertStringNotContainsString('sk-test-secret-1234', (string)json_encode($serialized));

		$second = $this->profiles->create(
			$this->userId,
			'Backup',
			'siliconflow',
			'sf-test-secret-9876',
			'Qwen/Qwen3-8B',
		);
		$this->assertFalse($second->getIsActive());

		$this->profiles->activate($this->userId, $second->getId());
		$this->assertTrue($this->profiles->getOwned($this->userId, $second->getId())->getIsActive());
		$this->assertFalse($this->profiles->getOwned($this->userId, $first->getId())->getIsActive());

		$updated = $this->profiles->update($this->userId, $first->getId(), 'DeepSeek renamed');
		$this->assertSame('DeepSeek renamed', $updated->getName());
		$this->assertSame('sk-test-secret-1234', $this->profiles->decrypt($updated));

		$updated = $this->profiles->update($this->userId, $first->getId(), null, null, 'sk-new-secret-5678');
		$this->assertSame('sk-new-secret-5678', $this->profiles->decrypt($updated));
		$this->assertStringEndsWith('5678', $this->profiles->serialize($updated)['apiKeyMasked']);

		$this->profiles->delete($this->userId, $second->getId());
		$this->assertCount(1, $this->profiles->list($this->userId));
	}

	public function testProfileOwnershipIsEnforced(): void {
		$profile = $this->profiles->create($this->userId, 'Mine', 'deepseek', 'sk-owner-1111');
		$other = \OC::$server->get(IUserManager::class)->createUser(
			'bt-ai-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);

		try {
			$this->expectException(NotFoundException::class);
			$this->profiles->getOwned($other->getUID(), $profile->getId());
		} finally {
			$other->delete();
		}
	}

	public function testContentLoggingIsOptInAndPersisted(): void {
		$this->assertFalse($this->settings->contentLogging($this->userId));
		$this->settings->setContentLogging($this->userId, true);
		$this->assertTrue($this->settings->contentLogging($this->userId));
		$this->settings->setContentLogging($this->userId, false);
		$this->assertFalse($this->settings->contentLogging($this->userId));
	}
}
