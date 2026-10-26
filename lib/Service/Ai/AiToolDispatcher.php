<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCA\BookTailor\Service\TagService;

class AiToolDispatcher {
	private const TOOLS = [
		'get_book',
		'list_chapters',
		'get_chapter',
		'list_assets',
		'get_asset_content',
		'list_tags',
		'get_structure',
	];

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookService $bookService,
		private readonly ChapterService $chapterService,
		private readonly AssetService $assetService,
		private readonly TagService $tagService,
		private readonly StructureService $structureService,
	) {
	}

	public function describe(): string {
		return implode(', ', self::TOOLS);
	}

	/**
	 * Execute one allowlisted read tool. Proposals are never executed here.
	 *
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	public function read(string $userId, int $bookId, string $tool, array $arguments): array {
		if (!in_array($tool, self::TOOLS, true)) {
			throw new ValidationException('Unknown AI read tool');
		}

		return match ($tool) {
			'get_book' => $this->getBook($userId, $bookId),
			'list_chapters' => $this->listChapters($userId, $bookId, $arguments),
			'get_chapter' => $this->getChapter($userId, $bookId, $arguments),
			'list_assets' => $this->listAssets($userId, $bookId, $arguments),
			'get_asset_content' => $this->getAssetContent($userId, $bookId, $arguments),
			'list_tags' => $this->listTags($userId, $bookId),
			'get_structure' => $this->getStructure($userId, $bookId, $arguments),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getBook(string $userId, int $bookId): array {
		$book = $this->bookService->get($userId, $bookId);

		return [
			'id' => $book->getId(),
			'name' => $book->getName(),
			'description' => $book->getDescription(),
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function listChapters(string $userId, int $bookId, array $arguments): array {
		$structureId = isset($arguments['structureId']) ? (int)$arguments['structureId'] : null;
		$tree = $this->chapterService->tree($userId, $bookId, $structureId);

		return ['chapters' => $this->serializeTree($tree)];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function getChapter(string $userId, int $bookId, array $arguments): array {
		$chapter = $this->chapter($userId, $bookId, $arguments);

		return [
			'id' => $chapter->getId(),
			'bookId' => $chapter->getBookId(),
			'parentId' => $chapter->getParentId(),
			'name' => $chapter->getName(),
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function listAssets(string $userId, int $bookId, array $arguments): array {
		$chapter = $this->chapter($userId, $bookId, $arguments);
		$assets = $this->assetService->list($userId, $chapter->getId())['assets'];
		$result = [];
		foreach ($assets as $asset) {
			$result[] = [
				'id' => $asset->getId(),
				'chapterId' => $asset->getChapterId(),
				'name' => $asset->getName(),
				'fileName' => $asset->getFileName(),
			];
		}

		return ['chapterId' => $chapter->getId(), 'assets' => $result];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function getAssetContent(string $userId, int $bookId, array $arguments): array {
		$assetId = (int)($arguments['assetId'] ?? 0);
		if ($assetId <= 0) {
			throw new ValidationException('A valid asset id is required');
		}

		$context = $this->assetService->get($userId, $assetId);
		if ($context['bookId'] !== $bookId) {
			throw new NotFoundException('Asset not found');
		}

		$content = $this->assetService->getContent($userId, $assetId);
		if (mb_strlen($content) > 12000) {
			$content = mb_substr($content, 0, 12000) . "\n\n[asset truncated]";
		}

		return [
			'id' => $context['asset']->getId(),
			'chapterId' => $context['chapter']->getId(),
			'name' => $context['asset']->getName(),
			'content' => $content,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function listTags(string $userId, int $bookId): array {
		$tags = [];
		foreach ($this->tagService->listForBook($userId, $bookId) as $tag) {
			$tags[] = ['id' => $tag->getId(), 'name' => $tag->getName()];
		}

		return ['tags' => $tags];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function getStructure(string $userId, int $bookId, array $arguments): array {
		$structureId = (int)($arguments['structureId'] ?? 0);
		if ($structureId <= 0) {
			throw new ValidationException('A valid structure id is required');
		}

		$structure = $this->structureService->getOwned($userId, $structureId);
		if ($structure->getBookId() !== $bookId) {
			throw new NotFoundException('Structure not found');
		}

		return [
			'id' => $structure->getId(),
			'name' => $structure->getName(),
			'isDefault' => $structure->getIsDefault(),
			'entries' => $this->structureService->getEntries($userId, $structureId),
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 */
	private function chapter(string $userId, int $bookId, array $arguments): Chapter {
		$chapterId = (int)($arguments['chapterId'] ?? 0);
		if ($chapterId <= 0) {
			throw new ValidationException('A valid chapter id is required');
		}

		$chapter = $this->chapterService->get($userId, $chapterId);
		if ($chapter->getBookId() !== $bookId) {
			throw new NotFoundException('Chapter not found');
		}

		return $chapter;
	}

	/**
	 * @param array<array-key, mixed> $nodes
	 * @return list<array{id: int, name: string, children: list<array>}>
	 * @psalm-suppress MixedAssignment
	 */
	private function serializeTree(array $nodes): array {
		$result = [];
		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['chapter']) || !$node['chapter'] instanceof Chapter) {
				continue;
			}
			$chapter = $node['chapter'];
			$children = $node['children'] ?? [];
			$result[] = [
				'id' => $chapter->getId(),
				'name' => $chapter->getName(),
				'children' => is_array($children) ? $this->serializeTree($children) : [],
			];
		}

		return $result;
	}

}
