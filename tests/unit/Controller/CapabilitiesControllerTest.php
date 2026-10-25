<?php

declare(strict_types=1);

namespace Controller;

use OCA\BookTailor\Controller\CapabilitiesController;
use OCP\App\IAppManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CapabilitiesControllerTest extends TestCase {
	public function testReportsEnabledEditorAndVersions(): void {
		$response = $this->controller('text', true)->index();

		$this->assertSame([
			'editor' => 'text',
			'versions' => true,
		], $response->getData());
	}

	public function testReportsDisabledEditor(): void {
		$response = $this->controller(null, false)->index();

		$this->assertSame([
			'editor' => null,
			'versions' => false,
		], $response->getData());
	}

	private function controller(?string $editor, bool $versions): CapabilitiesController {
		$user = $this->createMock(IUser::class);
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')
			->willReturnCallback(static function (string $appId) use ($editor, $versions): bool {
				return match ($appId) {
					'text' => $editor === 'text',
					'files_versions' => $versions,
					default => false,
				};
			});

		return new CapabilitiesController(
			$this->createMock(IRequest::class),
			$userSession,
			$this->createMock(LoggerInterface::class),
			$appManager,
		);
	}
}
