<?php

declare(strict_types=1);

namespace OCA\BookTailor\Controller;

use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\ImageService;
use OCA\BookTailor\Service\SerializerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class ImageController extends AbstractApiController {
	public function __construct(
		IRequest $request,
		IUserSession $userSession,
		LoggerInterface $logger,
		private readonly ImageService $imageService,
		private readonly SerializerService $serializer,
		private readonly IL10N $l10n,
	) {
		parent::__construct($request, $userSession, $logger);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/chapters/{chapterId}/images')]
	public function index(int $chapterId): DataResponse {
		return $this->handle(function () use ($chapterId): DataResponse {
			$result = $this->imageService->list($this->currentUserId(), $chapterId);

			return new DataResponse(array_map(
				fn (array $image): array => $this->serializer->chapterImage($image),
				$result['images'],
			));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/api/v1/chapters/{chapterId}/images')]
	public function upload(int $chapterId): DataResponse {
		return $this->handle(function () use ($chapterId): DataResponse {
			[$name, $content] = $this->uploadedFile();
			$result = $this->imageService->upload($this->currentUserId(), $chapterId, $name, $content);

			return new DataResponse($this->serializer->chapterImage($result['image']), Http::STATUS_CREATED);
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/chapters/{chapterId}/images/{fileId}')]
	public function replace(int $chapterId, int $fileId): DataResponse {
		return $this->handle(function () use ($chapterId, $fileId): DataResponse {
			[, $content] = $this->uploadedFile();
			$result = $this->imageService->replace($this->currentUserId(), $chapterId, $fileId, $content);

			return new DataResponse($this->serializer->chapterImage($result['image']));
		});
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/chapters/{chapterId}/images/{fileId}')]
	public function destroy(int $chapterId, int $fileId): DataResponse {
		return $this->handle(function () use ($chapterId, $fileId): DataResponse {
			$this->imageService->delete($this->currentUserId(), $chapterId, $fileId);

			return new DataResponse([], Http::STATUS_NO_CONTENT);
		});
	}

	/**
	 * @return array{string, string}
	 */
	private function uploadedFile(): array {
		/** @var array{name?: string, tmp_name?: string, error?: int, size?: int, type?: string}|null $upload */
		$upload = $this->request->getUploadedFile('file');
		$error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
		if ($upload === null || $error !== UPLOAD_ERR_OK) {
			throw new ValidationException(match ($error) {
				UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The image exceeds the server upload limit'),
				UPLOAD_ERR_PARTIAL => $this->l10n->t('The image upload was incomplete'),
				UPLOAD_ERR_NO_FILE => $this->l10n->t('An image file is required'),
				default => $this->l10n->t('The image could not be uploaded'),
			});
		}

		$tmpName = $upload['tmp_name'] ?? '';
		if ($tmpName === '' || !is_file($tmpName)) {
			throw new ValidationException($this->l10n->t('The uploaded file is unavailable'));
		}

		$content = file_get_contents($tmpName);
		if ($content === false) {
			throw new ValidationException($this->l10n->t('Could not read the uploaded image'));
		}

		return [$upload['name'] ?? '', $content];
	}
}
