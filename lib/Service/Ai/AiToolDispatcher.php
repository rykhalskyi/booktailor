<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterReferenceService;
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
		'collect_assets',
		'list_tags',
		'get_structure',
	];

	private const MAX_ASSET_CHARS = 12000;
	private const MAX_COLLECTED_CHARS = 48000;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookService $bookService,
		private readonly ChapterService $chapterService,
		private readonly ChapterReferenceService $chapterReferenceService,
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
	public function read(
		string $userId,
		int $bookId,
		string $tool,
		array $arguments,
		?int $structureId = null,
	): array {
		if (!in_array($tool, self::TOOLS, true)) {
			throw new ValidationException('Unknown AI read tool');
		}

		return match ($tool) {
			'get_book' => $this->getBook($userId, $bookId),
			'list_chapters' => $this->listChapters($userId, $bookId, $arguments, $structureId),
			'get_chapter' => $this->getChapter($userId, $bookId, $arguments, $structureId),
			'list_assets' => $this->listAssets($userId, $bookId, $arguments, $structureId),
			'get_asset_content' => $this->getAssetContent($userId, $bookId, $arguments, $structureId),
			'collect_assets' => $this->collectAssets($userId, $bookId, $arguments, $structureId),
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
	private function listChapters(string $userId, int $bookId, array $arguments, ?int $structureId): array {
		$requestedStructureId = isset($arguments['structureId'])
			? (int)$arguments['structureId']
			: $structureId;
		$tree = $this->chapterReferenceService->tree($userId, $bookId, $requestedStructureId);
		$chapters = $this->serializeTree($tree);

		return [
			'structureId' => $requestedStructureId,
			'chapters' => $chapters,
			'flat' => array_map(static fn (array $node): array => [
				'id' => $node['chapter']->getId(),
				'number' => $node['number'],
				'path' => $node['path'],
				'name' => $node['chapter']->getName(),
			], $this->chapterReferenceService->flatten($tree)),
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function getChapter(string $userId, int $bookId, array $arguments, ?int $structureId): array {
		$chapter = $this->chapter($userId, $bookId, $arguments, $structureId);
		$reference = $this->chapterInfo($userId, $bookId, $structureId, $chapter->getId());

		return [
			'id' => $chapter->getId(),
			'bookId' => $chapter->getBookId(),
			'parentId' => $chapter->getParentId(),
			'name' => $chapter->getName(),
			'number' => $reference['number'] ?? null,
			'path' => $reference['path'] ?? $chapter->getName(),
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function listAssets(string $userId, int $bookId, array $arguments, ?int $structureId): array {
		$chapter = $this->chapter($userId, $bookId, $arguments, $structureId);
		$list = $this->assetService->list($userId, $chapter->getId());
		$reference = $this->chapterInfo($userId, $bookId, $structureId, $chapter->getId());
		$result = [];
		foreach ($list['assets'] as $asset) {
			$result[] = [
				'id' => $asset->getId(),
				'chapterId' => $asset->getChapterId(),
				'name' => $asset->getName(),
				'fileName' => $asset->getFileName(),
				'tags' => $this->serializeTags($list['tagsByAsset'][$asset->getId()] ?? []),
			];
		}

		return [
			'chapterId' => $chapter->getId(),
			'chapterNumber' => $reference['number'] ?? null,
			'chapterPath' => $reference['path'] ?? $chapter->getName(),
			'assets' => $result,
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 */
	private function getAssetContent(string $userId, int $bookId, array $arguments, ?int $structureId): array {
		$assetId = (int)($arguments['assetId'] ?? 0);
		if ($assetId <= 0) {
			throw new ValidationException('A valid asset id is required');
		}

		$context = $this->assetService->get($userId, $assetId);
		if ($context['bookId'] !== $bookId) {
			throw new NotFoundException('Asset not found');
		}

		$content = $this->assetService->getContent($userId, $assetId);
		$truncated = mb_strlen($content) > self::MAX_ASSET_CHARS;
		if ($truncated) {
			$content = mb_substr($content, 0, self::MAX_ASSET_CHARS) . "\n\n[asset truncated]";
		}

		$reference = $this->chapterInfo($userId, $bookId, $structureId, $context['chapter']->getId());

		return [
			'id' => $context['asset']->getId(),
			'chapterId' => $context['chapter']->getId(),
			'chapterNumber' => $reference['number'] ?? null,
			'chapterPath' => $reference['path'] ?? $context['chapter']->getName(),
			'name' => $context['asset']->getName(),
			'tags' => $this->serializeTags($context['tags']),
			'content' => $content,
			'truncated' => $truncated,
		];
	}

	/**
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>
	 * @psalm-suppress MixedAssignment, MixedArgument
	 */
	private function collectAssets(string $userId, int $bookId, array $arguments, ?int $structureId): array {
		$requestedStructureId = isset($arguments['structureId'])
			? (int)$arguments['structureId']
			: $structureId;
		$includeDescendants = $this->boolValue($arguments['includeDescendants'] ?? false);
		$tagMode = $arguments['tagMode'] ?? 'any';
		if (!is_string($tagMode) || !in_array($tagMode, ['any', 'all'], true)) {
			throw new ValidationException('tagMode must be either "any" or "all"');
		}
		$tagNames = $this->stringList($arguments['tagNames'] ?? []);
		$requestedTagIds = $this->intList($arguments['tagIds'] ?? []);
		$chapterNumbers = $this->stringList($arguments['chapterNumbers'] ?? []);
		$chapterNames = $this->stringList($arguments['chapterNames'] ?? []);
		$requestedChapterIds = $this->intList($arguments['chapterIds'] ?? []);

		if ($chapterNumbers === [] && $chapterNames === [] && $requestedChapterIds === []) {
			throw new ValidationException('At least one chapter reference is required');
		}

		$tree = $this->chapterReferenceService->tree($userId, $bookId, $requestedStructureId);
		$flat = $this->chapterReferenceService->flatten($tree);
		$nodesById = [];
		foreach ($flat as $node) {
			$nodesById[$node['chapter']->getId()] = $node;
		}

		$selected = [];
		$unresolved = [];
		$ambiguous = [];

		foreach (array_merge($chapterNumbers, $chapterNames) as $reference) {
			$matches = $this->chapterReferenceService->matches($userId, $bookId, $requestedStructureId, $reference);
			if ($matches === []) {
				$unresolved[] = ['reference' => $reference, 'reason' => 'not found'];
				continue;
			}
			if (count($matches) > 1) {
				$ambiguous[] = [
					'reference' => $reference,
					'candidates' => array_map(static fn (array $node): array => [
						'id' => $node['chapter']->getId(),
						'number' => $node['number'],
						'path' => $node['path'],
						'name' => $node['chapter']->getName(),
					], $matches),
				];
				continue;
			}
			$selected[$matches[0]['chapter']->getId()] = $matches[0];
		}

		foreach ($requestedChapterIds as $chapterId) {
			if (isset($nodesById[$chapterId])) {
				$selected[$chapterId] = $nodesById[$chapterId];
			} else {
				$unresolved[] = ['chapterId' => $chapterId, 'reason' => 'not found in the active structure'];
			}
		}

		$finalNodes = [];
		foreach ($selected as $chapterId => $node) {
			$withDescendants = $includeDescendants
				? $this->chapterReferenceService->withDescendants($userId, $bookId, $requestedStructureId, $chapterId)
				: [$node];
			foreach ($withDescendants as $candidate) {
				$finalNodes[$candidate['chapter']->getId()] = $candidate;
			}
		}

		$bookTags = $this->tagService->listForBook($userId, $bookId);
		$tagsById = [];
		$tagsByName = [];
		foreach ($bookTags as $tag) {
			$tagsById[$tag->getId()] = $tag;
			$tagsByName[mb_strtolower($tag->getName())] = $tag;
			$tagsByName[mb_strtolower($tag->getSlug())] = $tag;
		}

		$unknownTags = [];
		$filterTagIds = [];
		foreach ($tagNames as $tagName) {
			$tag = $tagsByName[mb_strtolower($tagName)] ?? null;
			if ($tag === null) {
				$unknownTags[] = $tagName;
				continue;
			}
			$filterTagIds[$tag->getId()] = true;
		}
		foreach ($requestedTagIds as $tagId) {
			if (!isset($tagsById[$tagId])) {
				$unknownTags[] = 'id:' . $tagId;
				continue;
			}
			$filterTagIds[$tagId] = true;
		}

		$hasTagFilterRequest = $tagNames !== [] || $requestedTagIds !== [];
		$assets = [];
		$totalChars = 0;
		foreach ($finalNodes as $node) {
			$list = $this->assetService->list($userId, $node['chapter']->getId());
			foreach ($list['assets'] as $asset) {
				$tags = $this->serializeTags($list['tagsByAsset'][$asset->getId()] ?? []);
				if ($hasTagFilterRequest && $filterTagIds === []) {
					continue;
				}
				if ($filterTagIds !== []) {
					$assetTagIds = array_map(static fn (array $tag): int => $tag['id'], $tags);
					$matches = array_values(array_intersect($assetTagIds, array_keys($filterTagIds)));
					$matchesAll = count($matches) === count($filterTagIds);
					$matchesAny = $matches !== [];
					if (($tagMode === 'all' && !$matchesAll) || ($tagMode === 'any' && !$matchesAny)) {
						continue;
					}
				}

				$content = $this->assetService->getContent($userId, $asset->getId());
				$truncated = mb_strlen($content) > self::MAX_ASSET_CHARS;
				if ($truncated) {
					$content = mb_substr($content, 0, self::MAX_ASSET_CHARS) . "\n\n[asset truncated]";
				}
				$omitted = false;
				if ($totalChars + mb_strlen($content) > self::MAX_COLLECTED_CHARS) {
					$content = '';
					$omitted = true;
				} else {
					$totalChars += mb_strlen($content);
				}

				$assets[] = [
					'id' => $asset->getId(),
					'chapterId' => $node['chapter']->getId(),
					'chapterNumber' => $node['number'],
					'chapterPath' => $node['path'],
					'name' => $asset->getName(),
					'tags' => $tags,
					'content' => $content,
					'truncated' => $truncated,
					'contentOmitted' => $omitted,
				];
			}
		}

		return [
			'structureId' => $requestedStructureId,
			'chapters' => array_map(static fn (array $node): array => [
				'id' => $node['chapter']->getId(),
				'number' => $node['number'],
				'path' => $node['path'],
				'name' => $node['chapter']->getName(),
			], array_values($finalNodes)),
			'assets' => $assets,
			'filters' => [
				'tagNames' => $tagNames,
				'tagIds' => array_map('intval', array_keys($filterTagIds)),
				'tagMode' => $tagMode,
				'includeDescendants' => $includeDescendants,
			],
			'issues' => [
				'unresolved' => $unresolved,
				'ambiguous' => $ambiguous,
				'unknownTags' => $unknownTags,
			],
			'availableTags' => array_map(static fn ($tag): array => [
				'id' => $tag->getId(),
				'name' => $tag->getName(),
				'slug' => $tag->getSlug(),
			], $bookTags),
			'totalContentChars' => $totalChars,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function listTags(string $userId, int $bookId): array {
		$tags = [];
		foreach ($this->tagService->listForBook($userId, $bookId) as $tag) {
			$tags[] = [
				'id' => $tag->getId(),
				'name' => $tag->getName(),
				'slug' => $tag->getSlug(),
			];
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
	private function chapter(string $userId, int $bookId, array $arguments, ?int $structureId): Chapter {
		/** @psalm-suppress MixedAssignment */
		$reference = $arguments['chapter'] ?? $arguments['chapterName'] ?? $arguments['chapterNumber'] ?? null;
		if (is_string($reference) && trim($reference) !== '') {
			$node = $this->chapterReferenceService->resolve($userId, $bookId, $structureId, $reference);

			return $node['chapter'];
		}

		$chapterId = (int)($arguments['chapterId'] ?? 0);
		if ($chapterId <= 0) {
			throw new ValidationException('A valid chapter id or chapter reference is required');
		}

		$chapter = $this->chapterService->get($userId, $chapterId);
		if ($chapter->getBookId() !== $bookId) {
			throw new NotFoundException('Chapter not found');
		}

		return $chapter;
	}

	/**
	 * @return array{number: string, path: string}|null
	 */
	private function chapterInfo(string $userId, int $bookId, ?int $structureId, int $chapterId): ?array {
		try {
			$node = $this->chapterReferenceService->findByChapterId($userId, $bookId, $structureId, $chapterId);

			return ['number' => $node['number'], 'path' => $node['path']];
		} catch (NotFoundException) {
			return null;
		}
	}

	/**
	 * @param list<array{id: int, bookId: int, name: string, slug: string, color: string}> $tags
	 * @return list<array{id: int, name: string, slug: string}>
	 */
	private function serializeTags(array $tags): array {
		return array_map(static fn (array $tag): array => [
			'id' => $tag['id'],
			'name' => $tag['name'],
			'slug' => $tag['slug'],
		], $tags);
	}

	/**
	 * @param list<array{chapter: Chapter, number: string, path: string, children: list<array>}> $nodes
	 * @return list<array{id: int, number: string, path: string, name: string, children: list<array>}>
	 * @psalm-suppress MixedArgumentTypeCoercion
	 */
	private function serializeTree(array $nodes): array {
		$result = [];
		foreach ($nodes as $node) {
			$children = $node['children'];
			$result[] = [
				'id' => $node['chapter']->getId(),
				'number' => $node['number'],
				'path' => $node['path'],
				'name' => $node['chapter']->getName(),
				'children' => $this->serializeTree($children),
			];
		}

		return $result;
	}

	private function boolValue(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_string($value)) {
			$normalized = mb_strtolower(trim($value));
			if (in_array($normalized, ['1', 'true', 'yes'], true)) {
				return true;
			}
			if (in_array($normalized, ['0', 'false', 'no', ''], true)) {
				return false;
			}
		}
		if (is_int($value)) {
			return $value !== 0;
		}

		return false;
	}

	/**
	 * @return list<int>
	 * @psalm-suppress MixedAssignment
	 */
	private function intList(mixed $value): array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new ValidationException('Expected a list of ids');
		}

		$result = [];
		foreach ($value as $item) {
			$id = filter_var($item, FILTER_VALIDATE_INT);
			if ($id === false || $id <= 0) {
				throw new ValidationException('Every id must be a positive integer');
			}
			if (!in_array($id, $result, true)) {
				$result[] = $id;
			}
		}

		return $result;
	}

	/**
	 * @return list<string>
	 * @psalm-suppress MixedAssignment
	 */
	private function stringList(mixed $value): array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new ValidationException('Expected a list of strings');
		}

		$result = [];
		foreach ($value as $item) {
			if (!is_string($item) && !is_int($item) && !is_float($item)) {
				throw new ValidationException('Every value must be a string or number');
			}
			$item = trim((string)$item);
			if ($item !== '' && !in_array($item, $result, true)) {
				$result[] = $item;
			}
		}

		return $result;
	}
}
