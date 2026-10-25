<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Tag;
use OCA\BookTailor\Service\SerializerService;
use OCA\BookTailor\Service\TagService;
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
class TagController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly TagService $tagService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/books/{bookId}/tags')]
	public function index(int $bookId): DataResponse {
		return $this->handle(function () use ($bookId): DataResponse {
			$tags = $this->tagService->listForBook($this->currentUserId(), $bookId);

			return new DataResponse(array_map(
				fn (Tag $tag): array => $this->serializer->tag($tag),
				$tags,
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/books/{bookId}/tags')]
	public function create(int $bookId, string $name, ?string $color = null): DataResponse {
		return $this->handle(function () use ($bookId, $name, $color): DataResponse {
			$tag = $this->tagService->create($this->currentUserId(), $bookId, $name, $color);

			return new DataResponse($this->serializer->tag($tag), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/tags/{tagId}')]
	public function update(int $tagId, ?string $name = null, ?string $color = null): DataResponse {
		return $this->handle(function () use ($tagId, $name, $color): DataResponse {
			$tag = $this->tagService->update($this->currentUserId(), $tagId, $name, $color);

			return new DataResponse($this->serializer->tag($tag));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/tags/{tagId}')]
	public function destroy(int $tagId): DataResponse {
		return $this->handle(function () use ($tagId): DataResponse {
			$this->tagService->delete($this->currentUserId(), $tagId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/assets/{assetId}/tags')]
	public function listAssetTags(int $assetId): DataResponse {
		return $this->handle(function () use ($assetId): DataResponse {
			$tags = $this->tagService->listForAsset($this->currentUserId(), $assetId);

			return new DataResponse(array_map(
				fn (Tag $tag): array => $this->serializer->tag($tag),
				$tags,
			));
		});
	}

	/**
	 * @param list<int> $tagIds
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/assets/{assetId}/tags')]
	public function replaceAssetTags(int $assetId, array $tagIds = []): DataResponse {
		return $this->handle(function () use ($assetId, $tagIds): DataResponse {
			$tags = $this->tagService->replaceForAsset($this->currentUserId(), $assetId, $tagIds);

			return new DataResponse(array_map(
				fn (Tag $tag): array => $this->serializer->tag($tag),
				$tags,
			));
		});
	}
}
