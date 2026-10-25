<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Db\StructureChapterMapper;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\Files\Folder;
use OCP\IDBConnection;

class ChapterService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly ChapterMapper $chapterMapper,
		private readonly StructureChapterMapper $structureChapterMapper,
		private readonly StructureTreeBuilder $structureTreeBuilder,
		private readonly NumberingService $numberingService,
		private readonly BookService $bookService,
		private readonly StructureService $structureService,
		private readonly StorageService $storage,
		private readonly IDBConnection $db,
	) {
	}

	public function get(string $userId, int $chapterId): Chapter {
		$chapter = $this->chapterMapper->findById($chapterId);
		if ($chapter === null) {
			throw new NotFoundException('Chapter not found');
		}

		$this->bookService->get($userId, $chapter->getBookId());

		return $chapter;
	}

	/**
	 * @return list<array{chapter: Chapter, children: array, effectiveParentId?: ?int, number?: string}>
	 */
	public function tree(
		string $userId,
		int $bookId,
		?int $structureId = null,
		?int $parentId = null,
		?int $depth = null,
	): array {
		$this->bookService->get($userId, $bookId);
		$chapters = $this->chapterMapper->findByBook($bookId);

		if ($structureId !== null) {
			$structure = $this->structureService->getOwned($userId, $structureId);
			if ($structure->getBookId() !== $bookId) {
				throw new NotFoundException('Structure not found');
			}

			$entries = $this->structureChapterMapper->findByStructure($structureId);
			$tree = $this->structureTreeBuilder->build($chapters, $entries)['tree'];

			return $this->numberingService->number($tree);
		}

		if ($parentId !== null) {
			$parent = $this->chapterMapper->findByIdAndBook($parentId, $bookId);
			if ($parent === null) {
				throw new NotFoundException('Chapter not found');
			}
		}

		return $this->buildCanonicalTree($chapters, $parentId, $depth);
	}

	public function create(string $userId, int $bookId, string $name, ?int $parentId = null): Chapter {
		$book = $this->bookService->get($userId, $bookId);
		$name = $this->validateName($name);

		if ($parentId !== null) {
			$parent = $this->chapterMapper->findByIdAndBook($parentId, $bookId);
			if ($parent === null) {
				throw new NotFoundException('Parent chapter not found');
			}
			$parentFolder = $this->storage->getChapterFolderForUser($userId, $parent);
		} else {
			$parentFolder = $this->storage->getBookFolder($book);
		}

		$folder = $this->storage->createChapterFolder($parentFolder, $name);

		$this->db->beginTransaction();
		try {
			$now = new DateTime();
			$chapter = new Chapter();
			$chapter->setBookId($bookId);
			$chapter->setParentId($parentId);
			$chapter->setName($name);
			$chapter->setFileId($folder->getId());
			$chapter->setCreatedAt($now);
			$chapter->setUpdatedAt($now);
			$chapter = $this->chapterMapper->insert($chapter);

			$this->structureService->addChapterToStructures($bookId, $chapter->getId(), $parentId);
			$this->db->commit();

			return $chapter;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$this->storage->deleteNodeIfExists($folder);
			throw $e;
		}
	}

	public function rename(string $userId, int $chapterId, string $name): Chapter {
		$chapter = $this->get($userId, $chapterId);
		$name = $this->validateName($name);
		$oldName = $chapter->getName();
		$folder = $this->storage->getChapterFolderForUser($userId, $chapter);
		$this->storage->renameNode($folder, $name);

		try {
			$chapter->setName($name);
			$chapter->setUpdatedAt(new DateTime());

			return $this->chapterMapper->update($chapter);
		} catch (\Throwable $e) {
			try {
				$this->storage->renameNode($folder, $oldName);
			} catch (\Throwable) {
				// Best-effort rollback of the visible name.
			}
			throw $e;
		}
	}

	public function move(string $userId, int $chapterId, ?int $parentId): Chapter {
		$chapter = $this->get($userId, $chapterId);
		$currentParentId = $chapter->getParentId();
		if ($parentId === $currentParentId) {
			return $chapter;
		}

		$book = $this->bookService->get($userId, $chapter->getBookId());
		$chapters = $this->chapterMapper->findByBook($book->getId());
		/** @var array<int, Chapter> $chapterById */
		$chapterById = [];
		/** @var array<int, list<Chapter>> $siblingsByParent */
		$siblingsByParent = [];
		foreach ($chapters as $candidate) {
			$chapterById[$candidate->getId()] = $candidate;
			$siblingsByParent[$candidate->getParentId() ?? 0][] = $candidate;
		}
		foreach ($siblingsByParent as &$siblings) {
			usort($siblings, static fn (Chapter $a, Chapter $b): int => $a->getId() <=> $b->getId());
		}
		unset($siblings);

		$targetParent = null;
		if ($parentId !== null) {
			$targetParent = $chapterById[$parentId] ?? null;
			if ($targetParent === null) {
				throw new NotFoundException('Parent chapter not found');
			}

			for ($cursor = $targetParent; $cursor !== null;) {
				if ($cursor->getId() === $chapterId) {
					throw new ConflictException('A chapter cannot be moved under itself or its descendant');
				}
				$cursorId = $cursor->getParentId();
				$cursor = $cursorId === null ? null : ($chapterById[$cursorId] ?? null);
			}
		}

		$currentParent = $currentParentId === null ? null : ($chapterById[$currentParentId] ?? null);
		$grandParentId = $currentParent?->getParentId();
		$previousSiblingId = null;
		foreach ($siblingsByParent[$currentParentId ?? 0] ?? [] as $sibling) {
			if ($sibling->getId() === $chapterId) {
				break;
			}
			$previousSiblingId = $sibling->getId();
		}

		$parentSiblingIds = [];
		if ($currentParentId !== null) {
			foreach ($siblingsByParent[$grandParentId ?? 0] ?? [] as $siblingOfParent) {
				if ($siblingOfParent->getId() !== $currentParentId) {
					$parentSiblingIds[] = $siblingOfParent->getId();
				}
			}
		}

		$allowed = $parentId === $grandParentId
			|| ($previousSiblingId !== null && $parentId === $previousSiblingId)
			|| in_array($parentId, $parentSiblingIds, true);
		if (!$allowed) {
			throw new ConflictException('Chapter movement is not allowed');
		}

		$folder = $this->storage->getChapterFolderForUser($userId, $chapter);
		$oldParent = $folder->getParent();
		if (!$oldParent instanceof Folder) {
			throw new NotFoundException('Chapter folder parent not found');
		}
		$targetFolder = $this->storage->getBookFolder($book);
		if ($parentId !== null) {
			if ($targetParent === null) {
				throw new NotFoundException('Parent chapter not found');
			}
			$targetFolder = $this->storage->getChapterFolderForUser($userId, $targetParent);
		}

		$moved = false;
		$this->db->beginTransaction();
		try {
			$this->storage->moveNode($folder, $targetFolder);
			$moved = true;
			$this->structureService->moveChapterEntries($book->getId(), $chapterId, $parentId);
			$chapter->setParentId($parentId);
			$chapter->setUpdatedAt(new DateTime());
			$chapter = $this->chapterMapper->update($chapter);
			$this->db->commit();

			return $chapter;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			if ($moved) {
				try {
					$fileId = $chapter->getFileId();
					$currentFolder = $fileId === null
						? null
						: $this->storage->findNodeById($userId, $fileId, Folder::class);
					if ($currentFolder instanceof Folder) {
						$this->storage->moveNode($currentFolder, $oldParent);
					}
				} catch (\Throwable) {
					// Best-effort rollback of the folder move.
				}
			}
			throw $e;
		}
	}

	public function delete(string $userId, int $chapterId): void {
		$chapter = $this->get($userId, $chapterId);

		$fileId = $chapter->getFileId();
		if ($fileId !== null) {
			$folder = $this->storage->findNodeById($userId, $fileId, Folder::class);
			$this->storage->deleteNodeIfExists($folder);
		}

		$this->chapterMapper->delete($chapter);
	}

	/**
	 * @param list<Chapter> $chapters
	 * @return list<array{chapter: Chapter, children: array}>
	 */
	private function buildCanonicalTree(array $chapters, ?int $rootParentId, ?int $maxDepth): array {
		$childrenByParent = [];
		foreach ($chapters as $chapter) {
			$childrenByParent[$chapter->getParentId() ?? 0][] = $chapter;
		}
		foreach ($childrenByParent as &$siblings) {
			usort($siblings, static fn (Chapter $a, Chapter $b): int => $a->getId() <=> $b->getId());
		}
		unset($siblings);

		$build = function (?int $parentId, int $level) use (&$build, $childrenByParent, $maxDepth): array {
			/** @var list<array{chapter: Chapter, children: array}> $nodes */
			$nodes = [];
			foreach ($childrenByParent[$parentId ?? 0] ?? [] as $chapter) {
				/** @var list<array{chapter: Chapter, children: array}> $children */
				$children = ($maxDepth !== null && $level >= $maxDepth)
					? []
					: $build($chapter->getId(), $level + 1);
				$nodes[] = ['chapter' => $chapter, 'children' => $children];
			}

			return $nodes;
		};

		return $build($rootParentId, 1);
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Chapter name is required');
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Chapter name is too long');
		}

		return $name;
	}
}
