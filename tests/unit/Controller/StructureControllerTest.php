<?php

declare(strict_types=1);

namespace Controller;

use OCA\BookTailor\Controller\StructureController;
use OCA\BookTailor\Service\SerializerService;
use OCA\BookTailor\Service\StructureService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class StructureControllerTest extends TestCase {
	public function testCreateRequiresName(): void {
		$response = $this->controller()->create(1);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame('Structure name is required', $response->getData()['message']);
	}

	public function testUpdateRequiresName(): void {
		$response = $this->controller()->update(5);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
		$this->assertSame('Structure name is required', $response->getData()['message']);
	}

	private function controller(): StructureController {
		return new StructureController(
			$this->createMock(IRequest::class),
			$this->createMock(IUserSession::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(StructureService::class),
			$this->createMock(SerializerService::class),
		);
	}
}
