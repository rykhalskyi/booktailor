<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Service\VersionService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class VersionController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly VersionService $versionService,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/assets/{assetId}/versions')]
	public function index(int $assetId): DataResponse {
		return $this->handle(function () use ($assetId): DataResponse {
			return new DataResponse($this->versionService->list($this->currentUserId(), $assetId));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/assets/{assetId}/versions')]
	public function checkIn(int $assetId, ?string $message = null): DataResponse {
		return $this->handle(function () use ($assetId, $message): DataResponse {
			return new DataResponse($this->versionService->checkIn($this->currentUserId(), $assetId, $message));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/assets/{assetId}/versions/{revisionId}/restore')]
	public function restore(int $assetId, string $revisionId): DataResponse {
		return $this->handle(function () use ($assetId, $revisionId): DataResponse {
			return new DataResponse($this->versionService->restore($this->currentUserId(), $assetId, $revisionId));
		});
	}
}
