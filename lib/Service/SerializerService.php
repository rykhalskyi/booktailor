<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTimeInterface;
use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Entity\Composition;
use OCA\BookTailor\Entity\CompositionRule;
use OCA\BookTailor\Entity\Structure;
use OCA\BookTailor\Entity\Tag;

class SerializerService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly StorageService $storage,
	) {
	}

	public function book(Book $book): array {
		return [
			'id' => $book->getId(),
			'name' => $book->getName(),
			'description' => $book->getDescription(),
			'rootFileId' => $book->getRootFileId(),
			'path' => $this->storage->relativePathForFileId($book->getUserId(), $book->getRootFileId()),
			'createdAt' => $this->date($book->getCreatedAt()),
			'updatedAt' => $this->date($book->getUpdatedAt()),
		];
	}

	/**
	 * @param list<array> $children
	 */
	public function chapter(Chapter $chapter, array $children = [], ?string $number = null): array {
		return [
			'id' => $chapter->getId(),
			'bookId' => $chapter->getBookId(),
			'parentId' => $chapter->getParentId(),
			'name' => $chapter->getName(),
			'fileId' => $chapter->getFileId(),
			'number' => $number,
			'children' => $children,
			'createdAt' => $this->date($chapter->getCreatedAt()),
			'updatedAt' => $this->date($chapter->getUpdatedAt()),
		];
	}

	public function structure(Structure $structure): array {
		return [
			'id' => $structure->getId(),
			'bookId' => $structure->getBookId(),
			'name' => $structure->getName(),
			'isDefault' => $structure->getIsDefault(),
			'createdAt' => $this->date($structure->getCreatedAt()),
			'updatedAt' => $this->date($structure->getUpdatedAt()),
		];
	}

	public function structureEntry(int $chapterId, bool $included, int $position, ?int $effectiveParentId = null): array {
		return [
			'chapterId' => $chapterId,
			'included' => $included,
			'position' => $position,
			'effectiveParentId' => $effectiveParentId,
		];
	}

	/**
	 * @param list<array{id: int, bookId: int, name: string, slug: string, color: string}|Tag> $tags
	 */
	public function asset(Asset $asset, string $userId, int $bookId, array $tags = []): array {
		return [
			'id' => $asset->getId(),
			'bookId' => $bookId,
			'chapterId' => $asset->getChapterId(),
			'name' => $asset->getName(),
			'fileName' => $asset->getFileName(),
			'fileId' => $asset->getFileId(),
			'path' => $this->storage->relativePathForFileId($userId, $asset->getFileId()),
			'tags' => array_map(fn (Tag|array $tag): array => $this->tag($tag), $tags),
			'createdAt' => $this->date($asset->getCreatedAt()),
			'updatedAt' => $this->date($asset->getUpdatedAt()),
		];
	}

	/**
	 * @param array{fileId: int, name: string, mime: string, size: int, referenceCount: int} $image
	 */
	public function chapterImage(array $image): array {
		return [
			'fileId' => $image['fileId'],
			'name' => $image['name'],
			'mime' => $image['mime'],
			'size' => $image['size'],
			'path' => 'images/' . $image['name'],
			'references' => $image['referenceCount'],
		];
	}

	public function composition(Composition $composition, array $rules = [], bool $stale = false): array {
		return [
			'id' => $composition->getId(),
			'bookId' => $composition->getBookId(),
			'structureId' => $composition->getStructureId(),
			'structureName' => $composition->getStructureName(),
			'status' => $composition->getStatus(),
			'rules' => array_map(
				fn (CompositionRule $rule): array => $this->compositionRule($rule),
				$rules,
			),
			'outputFileId' => $composition->getOutputFileId(),
			'outputPath' => $composition->getOutputPath(),
			'error' => $composition->getError(),
			'stale' => $stale,
			'createdAt' => $this->date($composition->getCreatedAt()),
			'startedAt' => $this->date($composition->getStartedAt()),
			'completedAt' => $this->date($composition->getCompletedAt()),
		];
	}

	public function compositionRule(CompositionRule $rule): array {
		return [
			'tagId' => $rule->getTagId(),
			'priority' => $rule->getPriority(),
			'fallback' => $rule->getFallback(),
		];
	}

	public function tag(Tag|array $tag): array {
		if ($tag instanceof Tag) {
			return [
				'id' => $tag->getId(),
				'bookId' => $tag->getBookId(),
				'name' => $tag->getName(),
				'slug' => $tag->getSlug(),
				'color' => $tag->getColor(),
			];
		}

		return [
			'id' => (int)$tag['id'],
			'bookId' => (int)$tag['bookId'],
			'name' => (string)$tag['name'],
			'slug' => (string)$tag['slug'],
			'color' => (string)($tag['color'] ?? '#2563eb'),
		];
	}

	public function date(?DateTimeInterface $date): ?string {
		return $date?->format(DateTimeInterface::ATOM);
	}
}
