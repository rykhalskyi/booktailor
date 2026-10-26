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

class AiContextService {
	private const DEFAULT_MAX_CHARS = 48000;
	private const MAX_ASSET_CHARS = 12000;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookService $bookService,
		private readonly StructureService $structureService,
		private readonly ChapterService $chapterService,
		private readonly TagService $tagService,
		private readonly AssetService $assetService,
	) {
	}

	/**
	 * @param list<int> $contextAssetIds
	 * @return array{text: string, assetIds: list<int>, chapterIds: list<int>}
	 */
	public function build(
		string $userId,
		int $bookId,
		?int $structureId = null,
		?int $chapterId = null,
		?array $contextAssetIds = null,
		int $maxChars = self::DEFAULT_MAX_CHARS,
	): array {
		$book = $this->bookService->get($userId, $bookId);
		$structures = $this->structureService->listForBook($userId, $bookId);
		$tree = $this->chapterService->tree($userId, $bookId, $structureId);

		$parts = [];
		$parts[] = "## Book\n\nName: " . $book->getName()
			. ($book->getDescription() !== null ? "\nDescription: " . ($book->getDescription() ?? '') : '');

		$structureLines = ["\n## Structures\n"];
		foreach ($structures as $structure) {
			$structureLines[] = '- id=' . $structure->getId()
				. ' "' . $structure->getName() . '"'
				. ($structure->getIsDefault() ? ' (default)' : '');
		}
		$parts[] = implode("\n", $structureLines);

		$parts[] = "\n## Chapter tree\n\n" . $this->renderTree($tree);

		$tagLines = ["\n## Tags\n"];
		foreach ($this->tagService->listForBook($userId, $bookId) as $tag) {
			$tagLines[] = '- id=' . $tag->getId() . ' "' . $tag->getName() . '"';
		}
		$parts[] = implode("\n", $tagLines);

		$assetIds = [];
		$chapterIds = [];
		$assets = [];

		if ($contextAssetIds !== null && $contextAssetIds !== []) {
			foreach ($contextAssetIds as $rawId) {
				$assetId = $rawId;
				if ($assetId <= 0) {
					continue;
				}
				$context = $this->assetService->get($userId, $assetId);
				if ($context['bookId'] !== $bookId) {
					throw new ValidationException('An AI context asset does not belong to this book');
				}
				$assets[$context['asset']->getId()] = $context;
				$assetIds[] = $context['asset']->getId();
				$chapterIds[] = $context['chapter']->getId();
			}
		} elseif ($chapterId !== null) {
			$chapter = $this->chapterService->get($userId, $chapterId);
			if ($chapter->getBookId() !== $bookId) {
				throw new NotFoundException('Chapter not found');
			}
			$chapterIds[] = $chapterId;
			foreach ($this->assetService->list($userId, $chapterId)['assets'] as $asset) {
				$context = $this->assetService->get($userId, $asset->getId());
				$assets[$asset->getId()] = $context;
				$assetIds[] = $asset->getId();
			}
		}

		$assetSection = ["\n## Assets in scope\n"];
		foreach ($assets as $assetContext) {
			$asset = $assetContext['asset'];
			$chapter = $assetContext['chapter'];
			$content = $this->assetService->getContent($userId, $asset->getId());
			$truncated = mb_strlen($content) > self::MAX_ASSET_CHARS;
			if ($truncated) {
				$content = mb_substr($content, 0, self::MAX_ASSET_CHARS) . "\n\n[asset truncated]";
			}
			$assetSection[] = '### Asset id=' . $asset->getId() . ' "' . $asset->getName() . '"'
				. ' in chapter id=' . $chapter->getId() . ' "' . $chapter->getName() . "\"\n\n"
				. "```markdown\n" . $content . "\n```";
		}
		if ($assets === []) {
			$assetSection[] = '_No assets were preloaded. Use the read tools to inspect the book._';
		}
		$parts[] = implode("\n\n", $assetSection);

		$text = trim(implode("\n", $parts));
		if (mb_strlen($text) > $maxChars) {
			$text = mb_substr($text, 0, $maxChars) . "\n\n[context truncated]";
		}

		return [
			'text' => $text,
			'assetIds' => array_values(array_unique($assetIds)),
			'chapterIds' => array_values(array_unique($chapterIds)),
		];
	}

	/**
	 * @param array<array-key, mixed> $nodes
	 * @psalm-suppress MixedAssignment
	 */
	private function renderTree(array $nodes, int $depth = 0): string {
		$lines = [];
		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['chapter']) || !$node['chapter'] instanceof Chapter) {
				continue;
			}
			$chapter = $node['chapter'];
			$lines[] = str_repeat('  ', $depth) . '- id=' . $chapter->getId()
				. ' "' . $chapter->getName() . '"';
			$children = $node['children'] ?? [];
			if (is_array($children) && $children !== []) {
				$lines[] = $this->renderTree($children, $depth + 1);
			}
		}

		return implode("\n", $lines);
	}

}
