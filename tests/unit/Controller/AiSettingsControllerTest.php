<?php

declare(strict_types=1);

namespace Controller;

use OCA\BookTailor\Controller\AiSettingsController;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\Ai\AiProviderRegistry;
use OCA\BookTailor\Service\Ai\AiSettingsService;
use OCA\BookTailor\Service\LlmProfileService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

final class AiSettingsControllerTest extends TestCase {
	public function testLangfuseEndpointsAreAdminOnly(): void {
		foreach (['langfuse', 'updateLangfuse', 'clearLangfuse'] as $method) {
			$reflection = new ReflectionMethod(AiSettingsController::class, $method);
			$this->assertSame([], $reflection->getAttributes(NoAdminRequired::class));
		}

		$index = new ReflectionMethod(AiSettingsController::class, 'index');
		$this->assertNotSame([], $index->getAttributes(NoAdminRequired::class));
	}

	public function testUpdateLangfuseReturnsSettingsWithoutTheSecret(): void {
		$settings = [
			'configured' => true,
			'url' => 'https://langfuse.test',
			'publicKey' => 'pk-test',
			'promptLabel' => 'production',
			'secretConfigured' => true,
			'sources' => [
				'url' => 'app',
				'publicKey' => 'app',
				'secretKey' => 'app',
				'promptLabel' => 'none',
			],
		];
		$settingsService = $this->createMock(AiSettingsService::class);
		$settingsService->expects($this->once())
			->method('updateLangfuseSettings')
			->with('https://langfuse.test', 'pk-test', 'sk-test', 'production');
		$settingsService->method('langfuseSettings')->willReturn($settings);

		$response = $this->controller($settingsService)
			->updateLangfuse('https://langfuse.test', 'pk-test', 'sk-test', 'production');

		$this->assertSame($settings, $response->getData());
		$this->assertArrayNotHasKey('secretKey', $response->getData());
	}

	public function testUpdateLangfuseMapsValidationErrorsToUnprocessableEntity(): void {
		$settingsService = $this->createMock(AiSettingsService::class);
		$settingsService->method('updateLangfuseSettings')
			->willThrowException(new ValidationException('Langfuse URL must be a valid http(s) URL.'));

		$response = $this->controller($settingsService)->updateLangfuse('ftp://langfuse.test');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame('Langfuse URL must be a valid http(s) URL.', $response->getData()['message']);
	}

	private function controller(AiSettingsService $settingsService): AiSettingsController {
		return new AiSettingsController(
			$this->createMock(IRequest::class),
			$this->createMock(IUserSession::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(LlmProfileService::class),
			$settingsService,
			$this->createMock(AiProviderRegistry::class),
			$this->createMock(IGroupManager::class),
		);
	}
}
