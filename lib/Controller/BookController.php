<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\SerializerService;
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
class BookController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly BookService $bookService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books')]
	public function index(?int $limit = null, int $offset = 0): DataResponse {
		return $this->handle(function () use ($limit, $offset): DataResponse {
			$userId = $this->currentUserId();
			$books = $this->bookService->list($userId, $limit, $offset);

			return new DataResponse(array_map(
				fn (Book $book): array => $this->serializer->book($book),
				$books,
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}')]
	public function show(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			$book = $this->bookService->get($this->currentUserId(), $bookId);

			return new DataResponse($this->serializer->book($book));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books')]
	public function create(string $name, ?string $description = null): DataResponse {
		return $this->handle(function () use ($name, $description): DataResponse {
			$book = $this->bookService->create($this->currentUserId(), $name, $description);

			return new DataResponse($this->serializer->book($book), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/books/{bookId}')]
	public function update(int $bookId, string $name, ?string $description = null): DataResponse {
		return $this->handle(function () use ($bookId, $name, $description): DataResponse {
			$book = $this->bookService->update($this->currentUserId(), $bookId, $name, $description);

			return new DataResponse($this->serializer->book($book));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/books/{bookId}')]
	public function destroy(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			$this->bookService->delete($this->currentUserId(), $bookId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}
}
