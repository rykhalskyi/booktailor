<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\Files\File;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\Util;

class ImageService {
	public const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

	/**
	 * @var array<string, list<string>>
	 */
	public const MIME_EXTENSIONS = [
		'image/png' => ['png'],
		'image/jpeg' => ['jpg', 'jpeg'],
		'image/gif' => ['gif'],
		'image/webp' => ['webp'],
	];

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly ChapterService $chapterService,
		private readonly StorageService $storage,
		private readonly ImageReferenceParser $imageReferences,
		private readonly IMimeTypeDetector $mimeTypeDetector,
		private readonly IL10N $l10n,
	) {
	}

	/**
	 * @return array{bookId: int, images: list<array{fileId: int, name: string, mime: string, size: int, referenceCount: int}>}
	 */
	public function list(string $userId, int $chapterId): array {
		$chapter = $this->chapterService->get($userId, $chapterId);
		$folder = $this->storage->getChapterImagesFolder($userId, $chapter);
		if ($folder === null) {
			return ['bookId' => $chapter->getBookId(), 'images' => []];
		}

		$references = $this->referencesByName($userId, $chapter);
		/** @var array<string, array{fileId: int, name: string, mime: string, size: int, referenceCount: int}> $images */
		$images = [];
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof File) {
				continue;
			}
			$mime = $node->getMimeType();
			if (!isset(self::MIME_EXTENSIONS[$mime])) {
				continue;
			}

			$name = $node->getName();
			$images[$name] = [
				'fileId' => $node->getId(),
				'name' => $name,
				'mime' => $mime,
				'size' => (int)$node->getSize(),
				'referenceCount' => count($references[$name] ?? []),
			];
		}

		ksort($images, SORT_NATURAL | SORT_FLAG_CASE);

		return ['bookId' => $chapter->getBookId(), 'images' => array_values($images)];
	}

	/**
	 * @return array{bookId: int, image: array{fileId: int, name: string, mime: string, size: int, referenceCount: int}}
	 */
	public function upload(string $userId, int $chapterId, string $originalName, string $content): array {
		$chapter = $this->chapterService->get($userId, $chapterId);
		$folder = $this->storage->getChapterImagesFolder($userId, $chapter, true);
		if ($folder === null) {
			throw new ConflictException($this->l10n->t('Could not create the chapter images folder'));
		}

		$validated = $this->validate($originalName, $content);
		if ($folder->nodeExists($validated['name'])) {
			throw new ConflictException($this->l10n->t('An image with this name already exists in the chapter'));
		}

		$file = $folder->newFile($validated['name'], $content);
		$references = $this->referencesByName($userId, $chapter);

		return [
			'bookId' => $chapter->getBookId(),
			'image' => $this->describe($file, $references),
		];
	}

	/**
	 * @return array{bookId: int, image: array{fileId: int, name: string, mime: string, size: int, referenceCount: int}}
	 */
	public function replace(string $userId, int $chapterId, int $fileId, string $content): array {
		$chapter = $this->chapterService->get($userId, $chapterId);
		[, $file] = $this->resolveFile($userId, $chapter, $fileId);
		$name = $file->getName();
		$this->validate($name, $content, $name);

		$file->putContent($content);
		$references = $this->referencesByName($userId, $chapter);

		return [
			'bookId' => $chapter->getBookId(),
			'image' => $this->describe($file, $references),
		];
	}

	public function delete(string $userId, int $chapterId, int $fileId): void {
		$chapter = $this->chapterService->get($userId, $chapterId);
		[, $file] = $this->resolveFile($userId, $chapter, $fileId);
		$name = $file->getName();

		$references = $this->referencesByName($userId, $chapter);
		if (($references[$name] ?? []) !== []) {
			throw new ConflictException($this->l10n->t(
				'Image "%1$s" is referenced by: %2$s',
				[$name, implode(', ', $references[$name])],
			));
		}

		$file->delete();
	}

	/**
	 * @return array{name: string, mime: string}
	 */
	private function validate(string $originalName, string $content, ?string $forcedName = null): array {
		if ($content === '') {
			throw new ValidationException($this->l10n->t('The uploaded image is empty'));
		}

		$size = strlen($content);
		if ($size > $this->maxBytes()) {
			throw new ValidationException($this->l10n->t('The image is too large'));
		}

		$mime = $this->mimeTypeDetector->detectString($content);
		if (!isset(self::MIME_EXTENSIONS[$mime])) {
			throw new ValidationException($this->l10n->t('Only PNG, JPEG, GIF, and WebP images are accepted'));
		}

		if ($forcedName === null && (str_contains($originalName, '/') || str_contains($originalName, '\\'))) {
			throw new ValidationException($this->l10n->t('Image names must not contain path separators'));
		}

		$name = $forcedName ?? $originalName;
		$name = $this->storage->sanitizeName($name);
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

		if ($forcedName === null && $extension === '') {
			$name .= '.' . self::MIME_EXTENSIONS[$mime][0];
			$extension = self::MIME_EXTENSIONS[$mime][0];
		}

		if (!in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
			throw new ValidationException($this->l10n->t('The image type does not match the file name'));
		}

		return ['name' => $name, 'mime' => $mime];
	}

	private function maxBytes(): int {
		$limit = (int)Util::uploadLimit();
		if ($limit <= 0) {
			return self::MAX_IMAGE_BYTES;
		}

		return min(self::MAX_IMAGE_BYTES, $limit);
	}

	/**
	 * @return array{Chapter, File}
	 */
	private function resolveFile(string $userId, Chapter $chapter, int $fileId): array {
		$folder = $this->storage->getChapterImagesFolder($userId, $chapter);
		if ($folder === null) {
			throw new NotFoundException($this->l10n->t('Image not found'));
		}

		$file = $this->storage->findNodeById($userId, $fileId, File::class);
		if (!$file instanceof File || $file->getParent()->getId() !== $folder->getId()) {
			throw new NotFoundException($this->l10n->t('Image not found'));
		}

		return [$chapter, $file];
	}

	/**
	 * @param array<string, list<string>> $references
	 * @return array{fileId: int, name: string, mime: string, size: int, referenceCount: int}
	 */
	private function describe(File $file, array $references): array {
		return [
			'fileId' => $file->getId(),
			'name' => $file->getName(),
			'mime' => $file->getMimeType(),
			'size' => (int)$file->getSize(),
			'referenceCount' => count($references[$file->getName()] ?? []),
		];
	}

	/**
	 * @return array<string, list<string>>
	 */
	private function referencesByName(string $userId, Chapter $chapter): array {
		$folder = $this->storage->getChapterFolderForUser($userId, $chapter);
		$references = [];

		// One directory listing avoids a per-asset getById lookup while still
		// scanning every Markdown file that can reference a chapter image.
		foreach ($folder->getDirectoryListing() as $node) {
			if (!$node instanceof File || !str_ends_with(strtolower($node->getName()), '.md')) {
				continue;
			}

			try {
				$content = $node->getContent();
			} catch (\Throwable) {
				continue;
			}

			foreach ($this->imageReferences->extractNames($content) as $name) {
				$references[$name][] = $node->getName();
			}
		}

		foreach ($references as $name => $assets) {
			$references[$name] = array_values(array_unique($assets));
			sort($references[$name]);
		}

		return $references;
	}
}
