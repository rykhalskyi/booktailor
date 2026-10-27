<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\AssetMapper;
use OCA\BookTailor\Db\AssetTagMapper;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;

class AssetService {
	private const MAX_CONTENT_BYTES = 10 * 1024 * 1024;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly AssetMapper $assetMapper,
		private readonly AssetTagMapper $assetTagMapper,
		private readonly ChapterMapper $chapterMapper,
		private readonly ChapterService $chapterService,
		private readonly BookService $bookService,
		private readonly StorageService $storage,
	) {
	}

	/**
	 * @return array{assets: list<Asset>, bookId: int, tagsByAsset: array<int, list<array{id: int, bookId: int, name: string, slug: string, color: string}>>}
	 */
	public function list(string $userId, int $chapterId, ?int $limit = null, int $offset = 0): array {
		$chapter = $this->chapterService->get($userId, $chapterId);
		$assets = $this->assetMapper->findByChapter($chapterId, $limit, $offset);
		$assetIds = array_map(static fn (Asset $asset): int => $asset->getId(), $assets);

		return [
			'assets' => $assets,
			'bookId' => $chapter->getBookId(),
			'tagsByAsset' => $this->assetTagMapper->findTagsByAssetIds($assetIds),
		];
	}

	/**
	 * @return array{asset: Asset, bookId: int, chapter: Chapter, tags: list<array{id: int, bookId: int, name: string, slug: string, color: string}>}
	 */
	public function get(string $userId, int $assetId): array {
		$context = $this->context($userId, $assetId);
		$tags = $this->assetTagMapper->findTagsByAssetIds([$assetId])[$assetId] ?? [];

		return [
			'asset' => $context['asset'],
			'bookId' => $context['chapter']->getBookId(),
			'chapter' => $context['chapter'],
			'tags' => $tags,
		];
	}

	/**
	 * @return array{asset: Asset, bookId: int, chapter: Chapter, tags: list<array{id: int, bookId: int, name: string, slug: string, color: string}>}
	 */
	public function create(string $userId, int $chapterId, string $name): array {
		return $this->createWithContent($userId, $chapterId, $name, '');
	}

	/**
	 * @return array{asset: Asset, bookId: int, chapter: Chapter, tags: list<array{id: int, bookId: int, name: string, slug: string, color: string}>}
	 */
	public function upload(string $userId, int $chapterId, string $originalName, string $content): array {
		if (strlen($content) > self::MAX_CONTENT_BYTES) {
			throw new ValidationException('Markdown file is too large');
		}

		if (preg_match('/\.md$/i', trim($originalName)) !== 1) {
			throw new ValidationException('Only Markdown files are accepted');
		}

		$base = $this->storage->stripMarkdownExtension(basename($originalName));
		$name = $this->storage->sanitizeName($base);

		return $this->createWithContent($userId, $chapterId, $name, $content);
	}

	/**
	 * Create an asset with Markdown content. Used by AI confirmations and uploads.
	 *
	 * @return array{asset: Asset, bookId: int, chapter: Chapter, tags: list<array{id: int, bookId: int, name: string, slug: string, color: string}>}
	 */
	public function createWithContent(string $userId, int $chapterId, string $name, string $content): array {
		$chapter = $this->chapterService->get($userId, $chapterId);
		$name = $this->validateName($name);

		if ($this->assetMapper->findByChapterAndName($chapterId, $name) !== null) {
			throw new ValidationException('An asset with this name already exists in the chapter');
		}

		$folder = $this->storage->getChapterFolderForUser($userId, $chapter);
		$created = $this->storage->createAssetFile($folder, $name, $content);

		try {
			$now = new DateTime();
			$asset = new Asset();
			$asset->setChapterId($chapterId);
			$asset->setName($name);
			$asset->setFileName($created['fileName']);
			$asset->setFileId($created['file']->getId());
			$asset->setCreatedAt($now);
			$asset->setUpdatedAt($now);
			$asset = $this->assetMapper->insert($asset);
		} catch (\Throwable $e) {
			$this->storage->deleteNodeIfExists($created['file']);
			throw $e;
		}

		return [
			'asset' => $asset,
			'bookId' => $chapter->getBookId(),
			'chapter' => $chapter,
			'tags' => [],
		];
	}

	/**
	 * @return array{asset: Asset, bookId: int, chapter: Chapter, tags: list<array{id: int, bookId: int, name: string, slug: string, color: string}>}
	 */
	public function rename(string $userId, int $assetId, string $name): array {
		$context = $this->context($userId, $assetId);
		$asset = $context['asset'];
		$chapter = $context['chapter'];
		$name = $this->validateName($name);
		$oldName = $asset->getName();

		$duplicate = $this->assetMapper->findByChapterAndName($chapter->getId(), $name);
		if ($duplicate !== null && $duplicate->getId() !== $asset->getId()) {
			throw new ValidationException('An asset with this name already exists in the chapter');
		}

		$file = $this->storage->getAssetFile($userId, $asset);
		$newFileName = $this->storage->renameNode($file, $name, '.md');

		try {
			$asset->setName($name);
			$asset->setFileName($newFileName);
			$asset->setUpdatedAt(new DateTime());
			$asset = $this->assetMapper->update($asset);
		} catch (\Throwable $e) {
			try {
				$this->storage->renameNode($file, $oldName, '.md');
			} catch (\Throwable) {
				// Best-effort rollback of the visible name.
			}
			throw $e;
		}

		$tags = $this->assetTagMapper->findTagsByAssetIds([$assetId])[$assetId] ?? [];

		return [
			'asset' => $asset,
			'bookId' => $chapter->getBookId(),
			'chapter' => $chapter,
			'tags' => $tags,
		];
	}

	public function delete(string $userId, int $assetId): void {
		$context = $this->context($userId, $assetId);
		$asset = $context['asset'];

		$fileId = $asset->getFileId();
		if ($fileId !== null) {
			$file = $this->storage->findNodeById($userId, $fileId, \OCP\Files\File::class);
			$this->storage->deleteNodeIfExists($file);
		}

		$this->assetMapper->delete($asset);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getContent(string $userId, int $assetId): string {
		$context = $this->context($userId, $assetId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);

		return $file->getContent();
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function updateContent(string $userId, int $assetId, string $content): void {
		if (strlen($content) > self::MAX_CONTENT_BYTES) {
			throw new ValidationException('Markdown file is too large');
		}

		$context = $this->context($userId, $assetId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);
		$file->putContent($content);

		$asset = $context['asset'];
		$asset->setUpdatedAt(new DateTime());
		$this->assetMapper->update($asset);
	}

	/**
	 * @return array{asset: Asset, chapter: Chapter}
	 */
	public function context(string $userId, int $assetId): array {
		$asset = $this->assetMapper->findById($assetId);
		if ($asset === null) {
			throw new NotFoundException('Asset not found');
		}

		$chapter = $this->chapterMapper->findById($asset->getChapterId());
		if ($chapter === null) {
			throw new NotFoundException('Chapter not found');
		}

		$this->bookService->get($userId, $chapter->getBookId());

		return ['asset' => $asset, 'chapter' => $chapter];
	}

	private function validateName(string $name): string {
		$name = $this->storage->sanitizeName($this->storage->stripMarkdownExtension($name));
		if ($name === '') {
			throw new ValidationException('Asset name is required');
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Asset name is too long');
		}

		return $name;
	}
}
