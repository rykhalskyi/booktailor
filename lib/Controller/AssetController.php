<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
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
class AssetController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly AssetService $assetService,
		private readonly SerializerService $serializer,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/chapters/{chapterId}/assets')]
	public function index(int $chapterId, ?int $limit = null, int $offset = 0): DataResponse {
		return $this->handle(function () use ($chapterId, $limit, $offset): DataResponse {
			$result = $this->assetService->list($this->currentUserId(), $chapterId, $limit, $offset);

			return new DataResponse(array_map(
				fn (Asset $asset): array => $this->serializer->asset(
					$asset,
					$this->currentUserId(),
					$result['bookId'],
					$result['tagsByAsset'][$asset->getId()] ?? [],
				),
				$result['assets'],
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/assets/{assetId}')]
	public function show(int $assetId): DataResponse {
		return $this->handle(function () use ($assetId): DataResponse {
			$result = $this->assetService->get($this->currentUserId(), $assetId);

			return new DataResponse($this->serializer->asset(
				$result['asset'],
				$this->currentUserId(),
				$result['bookId'],
				$result['tags'],
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/assets/{assetId}/content')]
	public function content(int $assetId): DataResponse {
		return $this->handle(function () use ($assetId): DataResponse {
			return new DataResponse([
				'content' => $this->assetService->getContent($this->currentUserId(), $assetId),
			]);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/assets/{assetId}/content')]
	public function updateContent(int $assetId, string $content = ''): DataResponse {
		return $this->handle(function () use ($assetId, $content): DataResponse {
			$this->assetService->updateContent($this->currentUserId(), $assetId, $content);

			return new DataResponse([
				'content' => $this->assetService->getContent($this->currentUserId(), $assetId),
			]);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/chapters/{chapterId}/assets')]
	public function create(int $chapterId, string $name): DataResponse {
		return $this->handle(function () use ($chapterId, $name): DataResponse {
			$result = $this->assetService->create($this->currentUserId(), $chapterId, $name);

			return new DataResponse($this->serializer->asset(
				$result['asset'],
				$this->currentUserId(),
				$result['bookId'],
				$result['tags'],
			), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/chapters/{chapterId}/assets/upload')]
	public function upload(int $chapterId): DataResponse {
		return $this->handle(function () use ($chapterId): DataResponse {
			/** @var array{name?: string, tmp_name?: string, error?: int, size?: int, type?: string}|null $upload */
			$upload = $this->request->getUploadedFile('file');
			if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
				throw new ValidationException('A Markdown file is required');
			}

			$tmpName = $upload['tmp_name'] ?? '';
			if ($tmpName === '' || !is_file($tmpName)) {
				throw new ValidationException('Uploaded file is unavailable');
			}

			$content = file_get_contents($tmpName);
			if ($content === false) {
				throw new ValidationException('Could not read the uploaded file');
			}

			$result = $this->assetService->upload(
				$this->currentUserId(),
				$chapterId,
				$upload['name'] ?? '',
				$content,
			);

			return new DataResponse($this->serializer->asset(
				$result['asset'],
				$this->currentUserId(),
				$result['bookId'],
				$result['tags'],
			), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PATCH', url: '/api/v1/assets/{assetId}')]
	public function update(int $assetId, ?string $name = null, ?bool $includeInComposition = null): DataResponse {
		return $this->handle(function () use ($assetId, $name, $includeInComposition): DataResponse {
			if ($name === null && $includeInComposition === null) {
				throw new ValidationException('Provide a name or composition inclusion flag');
			}

			$result = $name !== null
				? $this->assetService->rename($this->currentUserId(), $assetId, $name)
				: $this->assetService->get($this->currentUserId(), $assetId);

			if ($includeInComposition !== null) {
				$result = $this->assetService->setIncludeInComposition(
					$this->currentUserId(),
					$assetId,
					$includeInComposition,
				);
			}

			return new DataResponse($this->serializer->asset(
				$result['asset'],
				$this->currentUserId(),
				$result['bookId'],
				$result['tags'],
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/assets/{assetId}')]
	public function destroy(int $assetId): DataResponse {
		return $this->handle(function () use ($assetId): DataResponse {
			$this->assetService->delete($this->currentUserId(), $assetId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}
}
