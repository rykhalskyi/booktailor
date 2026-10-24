<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\ChapterService;
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
class ChapterController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly ChapterService $chapterService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/chapters')]
	public function index(
		int $bookId,
		?int $structureId = null,
		?int $parentId = null,
		?int $depth = null,
	): DataResponse {
		return $this->handle(function () use ($bookId, $structureId, $parentId, $depth): DataResponse {
			$tree = $this->chapterService->tree(
				$this->currentUserId(),
				$bookId,
				$structureId,
				$parentId,
				$depth,
			);

			return new DataResponse($this->serializeTree($tree));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/chapters/{chapterId}')]
	public function show(int $chapterId): DataResponse {
		return $this->handle(function () use ($chapterId): DataResponse {
			$chapter = $this->chapterService->get($this->currentUserId(), $chapterId);

			return new DataResponse($this->serializer->chapter($chapter));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books/{bookId}/chapters')]
	public function create(int $bookId, string $name, ?int $parentId = null): DataResponse {
		return $this->handle(function () use ($bookId, $name, $parentId): DataResponse {
			$chapter = $this->chapterService->create($this->currentUserId(), $bookId, $name, $parentId);

			return new DataResponse($this->serializer->chapter($chapter), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/chapters/{chapterId}')]
	public function update(int $chapterId, ?string $name = null, ?int $parentId = null): DataResponse {
		return $this->handle(function () use ($chapterId, $name, $parentId): DataResponse {
			if ($name === null) {
				throw new ValidationException('Chapter name is required');
			}

			$chapter = $this->chapterService->rename(
				$this->currentUserId(),
				$chapterId,
				$name,
				$parentId,
			);

			return new DataResponse($this->serializer->chapter($chapter));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/chapters/{chapterId}')]
	public function destroy(int $chapterId): DataResponse {
		return $this->handle(function () use ($chapterId): DataResponse {
			$this->chapterService->delete($this->currentUserId(), $chapterId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	/**
	 * @param list<array{chapter: Chapter, children: array}> $nodes
	 * @return list<array>
	 */
	private function serializeTree(array $nodes): array {
		$serialized = [];
		foreach ($nodes as $node) {
			/** @psalm-suppress ArgumentTypeCoercion */
			$serialized[] = $this->serializer->chapter(
				$node['chapter'],
				$this->serializeTree($node['children']),
			);
		}

		return $serialized;
	}
}
