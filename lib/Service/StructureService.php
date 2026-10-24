<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Db\StructureChapterMapper;
use OCA\BookTailor\Db\StructureMapper;
use OCA\BookTailor\Entity\Structure;
use OCA\BookTailor\Exception\NotFoundException;

class StructureService {
	public const DEFAULT_NAME = 'Default';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly StructureMapper $structureMapper,
		private readonly StructureChapterMapper $structureChapterMapper,
		private readonly ChapterMapper $chapterMapper,
		private readonly BookMapper $bookMapper,
	) {
	}

	/** @psalm-suppress PossiblyUnusedReturnValue */
	public function createDefaultStructure(int $bookId): Structure {
		$existing = $this->structureMapper->findDefaultByBook($bookId);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTime();
		$structure = new Structure();
		$structure->setBookId($bookId);
		$structure->setName(self::DEFAULT_NAME);
		$structure->setIsDefault(true);
		$structure->setCreatedAt($now);
		$structure->setUpdatedAt($now);

		return $this->structureMapper->insert($structure);
	}

	/**
	 * @return list<Structure>
	 */
	public function listForBook(string $userId, int $bookId): array {
		$this->assertBookOwned($userId, $bookId);

		return $this->structureMapper->findByBook($bookId);
	}

	public function getOwned(string $userId, int $structureId): Structure {
		$structure = $this->structureMapper->findById($structureId);
		if ($structure === null) {
			throw new NotFoundException('Structure not found');
		}

		$this->assertBookOwned($userId, $structure->getBookId());

		return $structure;
	}

	/**
	 * @return list<array{chapterId: int, included: bool, position: int, effectiveParentId: ?int}>
	 */
	public function getEntries(string $userId, int $structureId): array {
		$structure = $this->getOwned($userId, $structureId);
		$rows = $this->structureChapterMapper->findByStructure($structureId);
		$chapters = $this->chapterMapper->findByBook($structure->getBookId());

		$parentById = [];
		foreach ($chapters as $chapter) {
			$parentById[$chapter->getId()] = $chapter->getParentId();
		}

		$included = [];
		foreach ($rows as $row) {
			if ($row['included']) {
				$included[$row['chapter_id']] = true;
			}
		}

		$effectiveParentCache = [];
		$effectiveParent = function (int $chapterId) use (&$effectiveParent, $parentById, $included, &$effectiveParentCache): ?int {
			if (array_key_exists($chapterId, $effectiveParentCache)) {
				return $effectiveParentCache[$chapterId];
			}

			$parentId = $parentById[$chapterId] ?? null;
			while ($parentId !== null) {
				if (isset($included[$parentId])) {
					return $effectiveParentCache[$chapterId] = $parentId;
				}
				$parentId = $parentById[$parentId] ?? null;
			}

			return $effectiveParentCache[$chapterId] = null;
		};

		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'chapterId' => $row['chapter_id'],
				'included' => $row['included'],
				'position' => $row['position'],
				'effectiveParentId' => $effectiveParent($row['chapter_id']),
			];
		}

		return $result;
	}

	public function addChapterToStructures(int $bookId, int $chapterId, ?int $parentId): void {
		$structures = $this->structureMapper->findByBook($bookId);
		foreach ($structures as $structure) {
			$position = $this->structureChapterMapper->nextPosition($structure->getId(), $bookId, $parentId);
			$this->structureChapterMapper->insertEntry($structure->getId(), $chapterId, true, $position);
		}
	}

	private function assertBookOwned(string $userId, int $bookId): void {
		if ($this->bookMapper->findByIdAndUser($bookId, $userId) === null) {
			throw new NotFoundException('Book not found');
		}
	}
}
