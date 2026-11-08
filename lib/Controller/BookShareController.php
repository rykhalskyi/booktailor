<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Service\BookShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class BookShareController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly BookShareService $shareService,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/shares')]
	public function index(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			return new DataResponse($this->shareService->listForBook($this->currentUserId(), $bookId));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books/{bookId}/shares')]
	public function create(int $bookId, string $userId, string $role): DataResponse {
		return $this->handle(function () use ($bookId, $userId, $role): DataResponse {
			$share = $this->shareService->create($this->currentUserId(), $bookId, $userId, $role);

			return new DataResponse($share, Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/books/{bookId}/shares/{shareId}')]
	public function update(int $bookId, int $shareId, string $role): DataResponse {
		return $this->handle(function () use ($bookId, $shareId, $role): DataResponse {
			$share = $this->shareService->update($this->currentUserId(), $bookId, $shareId, $role);

			return new DataResponse($share);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/books/{bookId}/shares/{shareId}')]
	public function destroy(int $bookId, int $shareId): DataResponse {
		return $this->handle(function () use ($bookId, $shareId): DataResponse {
			$this->shareService->delete($this->currentUserId(), $bookId, $shareId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/sharees')]
	public function sharees(int $bookId, string $search = ''): DataResponse {
		return $this->handle(function () use ($bookId, $search): DataResponse {
			return new DataResponse($this->shareService->searchRecipients($this->currentUserId(), $bookId, $search));
		});
	}
}
