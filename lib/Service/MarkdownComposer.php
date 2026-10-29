<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ValidationException;
use OCP\Files\File;
use OCP\Files\Folder;

class MarkdownComposer {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly StorageService $storage,
		private readonly ImageReferenceParser $imageReferences,
	) {
	}

	/**
	 * @param list<array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string}> $numberedTree
	 * @param array<int, list<Asset>> $selectedByChapter
	 * @return array{markdown: string, assetCount: int}
	 */
	public function compose(Book $book, array $numberedTree, array $selectedByChapter): array {
		$copiedImages = [];
		$assetCount = 0;
		$blocks = $this->renderNodes($book, $numberedTree, 1, $selectedByChapter, $copiedImages, $assetCount);

		if ($assetCount === 0) {
			throw new ValidationException('No assets matched the composition rules');
		}

		$markdown = '# ' . trim($book->getName()) . "\n\n" . implode("\n\n", $blocks);

		return [
			'markdown' => rtrim($markdown) . "\n",
			'assetCount' => $assetCount,
		];
	}

	/**
	 * @param array<array-key, mixed> $nodes
	 * @param array<int, list<Asset>> $selectedByChapter
	 * @param array<int, string> $copiedImages
	 * @return list<string>
	 */
	private function renderNodes(
		Book $book,
		array $nodes,
		int $depth,
		array $selectedByChapter,
		array &$copiedImages,
		int &$assetCount,
	): array {
		$blocks = [];
		$level = min($depth + 1, 6);

		/** @var array{chapter: Chapter, children: array<array-key, mixed>, effectiveParentId: ?int, number: string} $node */
		foreach ($nodes as $node) {
			$chapter = $node['chapter'];
			$parts = [
				str_repeat('#', $level) . ' ' . $node['number'] . ' ' . $chapter->getName(),
			];

			foreach ($selectedByChapter[$chapter->getId()] ?? [] as $asset) {
				$content = $this->readAsset($book, $chapter, $asset, $copiedImages);
				if ($content === '') {
					continue;
				}

				$parts[] = $content;
				$assetCount++;
			}

			$blocks[] = implode("\n\n", $parts);

			foreach ($this->renderNodes($book, $node['children'], $depth + 1, $selectedByChapter, $copiedImages, $assetCount) as $childBlock) {
				$blocks[] = $childBlock;
			}
		}

		return $blocks;
	}

	/**
	 * @param array<int, string> $copiedImages
	 */
	private function readAsset(Book $book, Chapter $chapter, Asset $asset, array &$copiedImages): string {
		$file = $this->storage->getAssetFile($book->getUserId(), $asset);
		$content = str_replace(["\r\n", "\r"], "\n", $file->getContent());
		$content = $this->rewriteImages($book, $chapter, $asset, $content, $copiedImages);

		return trim($content);
	}

	/**
	 * @param array<int, string> $copiedImages
	 */
	private function rewriteImages(Book $book, Chapter $chapter, Asset $asset, string $content, array &$copiedImages): string {
		$content = preg_replace_callback(
			'/(!\[[^\]]*\]\(\s*)([^)\s]+)(\s+(?:"[^"]*"|\'[^\']*\'|\([^)]*\)))?(\s*\))/',
			function (array $match) use ($book, $chapter, $asset, &$copiedImages): string {
				$rewritten = $this->rewriteImageUrl($book, $chapter, $asset, $match[2], $copiedImages);

				return $match[1] . $rewritten . ($match[3] ?? '') . $match[4];
			},
			$content,
		) ?? $content;

		return preg_replace_callback(
			'/(<img\b[^>]*\bsrc=)(["\'])([^"\']+)(\2)/i',
			function (array $match) use ($book, $chapter, $asset, &$copiedImages): string {
				$rewritten = $this->rewriteImageUrl($book, $chapter, $asset, $match[3], $copiedImages);

				return $match[1] . $match[2] . $rewritten . $match[4];
			},
			$content,
		) ?? $content;
	}

	/**
	 * @param array<int, string> $copiedImages
	 */
	private function rewriteImageUrl(Book $book, Chapter $chapter, Asset $asset, string $url, array &$copiedImages): string {
		$parsed = $this->imageReferences->parseUrl($url);
		if ($parsed === null) {
			return $url;
		}

		$name = $parsed['name'];
		$chapterFolder = $this->storage->getChapterFolderForUser($book->getUserId(), $chapter);
		if (!$chapterFolder->nodeExists('images')) {
			throw new ValidationException('Image "' . $name . '" referenced by "' . $asset->getName() . '" is missing');
		}

		$imagesFolder = $chapterFolder->get('images');
		if (!$imagesFolder instanceof Folder || !$imagesFolder->nodeExists($name)) {
			throw new ValidationException('Image "' . $name . '" referenced by "' . $asset->getName() . '" is missing');
		}

		$image = $imagesFolder->get($name);
		if (!$image instanceof File) {
			throw new ValidationException('Image "' . $name . '" referenced by "' . $asset->getName() . '" is missing');
		}

		$fileId = $image->getId();
		if (!isset($copiedImages[$fileId])) {
			$copiedImages[$fileId] = $this->storage->copyCompositionImage($book, $image);
		}

		$destination = 'images/' . rawurlencode($copiedImages[$fileId]) . $parsed['suffix'];

		return $parsed['wrapped'] ? '<' . $destination . '>' : $destination;
	}
}
