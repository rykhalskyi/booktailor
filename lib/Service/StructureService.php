<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Db\StructureChapterMapper;
use OCA\BookTailor\Db\StructureMapper;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Entity\Structure;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\IDBConnection;

class StructureService {
	public const DEFAULT_NAME = 'Default';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly StructureMapper $structureMapper,
		private readonly StructureChapterMapper $structureChapterMapper,
		private readonly StructureTreeBuilder $structureTreeBuilder,
		private readonly ChapterMapper $chapterMapper,
		private readonly BookMapper $bookMapper,
		private readonly IDBConnection $db,
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

	public function create(string $userId, int $bookId, string $name): Structure {
		$this->assertBookOwned($userId, $bookId);
		$name = $this->validateName($name);

		if ($this->structureMapper->findByBookAndName($bookId, $name) !== null) {
			throw new ConflictException('A structure with this name already exists');
		}

		$default = $this->structureMapper->findDefaultByBook($bookId);
		if ($default === null) {
			throw new NotFoundException('Default structure not found');
		}

		$this->db->beginTransaction();
		try {
			$now = new DateTime();
			$structure = new Structure();
			$structure->setBookId($bookId);
			$structure->setName($name);
			$structure->setIsDefault(false);
			$structure->setCreatedAt($now);
			$structure->setUpdatedAt($now);
			$structure = $this->structureMapper->insert($structure);

			foreach ($this->structureChapterMapper->findByStructure($default->getId()) as $entry) {
				$this->structureChapterMapper->insertEntry(
					$structure->getId(),
					$entry['chapter_id'],
					$entry['included'],
					$entry['position'],
				);
			}

			$this->db->commit();

			return $structure;
		} catch (\OCP\DB\Exception $e) {
			$this->db->rollBack();
			if (in_array($e->getReason(), [
				\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				\OCP\DB\Exception::REASON_CONSTRAINT_VIOLATION,
			], true)) {
				throw new ConflictException('A structure with this name already exists');
			}
			throw $e;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function rename(string $userId, int $structureId, string $name): Structure {
		$structure = $this->getOwned($userId, $structureId);
		$name = $this->validateName($name);

		$duplicate = $this->structureMapper->findByBookAndName($structure->getBookId(), $name);
		if ($duplicate !== null && $duplicate->getId() !== $structure->getId()) {
			throw new ConflictException('A structure with this name already exists');
		}

		$structure->setName($name);
		$structure->setUpdatedAt(new DateTime());

		try {
			return $this->structureMapper->update($structure);
		} catch (\OCP\DB\Exception $e) {
			if (in_array($e->getReason(), [
				\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				\OCP\DB\Exception::REASON_CONSTRAINT_VIOLATION,
			], true)) {
				throw new ConflictException('A structure with this name already exists');
			}
			throw $e;
		}
	}

	public function delete(string $userId, int $structureId): void {
		$structure = $this->getOwned($userId, $structureId);
		if ($structure->getIsDefault()) {
			throw new ConflictException('The default structure cannot be deleted');
		}

		$this->structureMapper->delete($structure);
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
		$effectiveParentIds = $this->structureTreeBuilder->build($chapters, $rows)['effectiveParentIds'];

		$result = [];
		foreach ($rows as $row) {
			$result[] = [
				'chapterId' => $row['chapter_id'],
				'included' => $row['included'],
				'position' => $row['position'],
				'effectiveParentId' => $effectiveParentIds[$row['chapter_id']] ?? null,
			];
		}

		return $result;
	}

	public function addChapterToStructures(int $bookId, int $chapterId, ?int $parentId): void {
		$structures = $this->structureMapper->findByBook($bookId);
		foreach ($structures as $structure) {
			$includedByChapter = [];
			foreach ($this->structureChapterMapper->findByStructure($structure->getId()) as $entry) {
				$includedByChapter[$entry['chapter_id']] = $entry['included'];
			}

			$included = $structure->getIsDefault()
				|| $parentId === null
				|| ($includedByChapter[$parentId] ?? false);
			$position = $this->structureChapterMapper->nextPosition($structure->getId(), $bookId, $parentId);
			$this->structureChapterMapper->insertEntry($structure->getId(), $chapterId, $included, $position);
		}
	}

	public function moveChapterEntries(int $bookId, int $chapterId, ?int $newParentId): void {
		foreach ($this->structureMapper->findByBook($bookId) as $structure) {
			$position = $this->structureChapterMapper->nextPosition($structure->getId(), $bookId, $newParentId);
			$this->structureChapterMapper->updatePosition($structure->getId(), $chapterId, $position);
		}
	}

	/**
	 * @param array<array-key, mixed> $entries
	 * @return list<array{chapterId: int, included: bool, position: int, effectiveParentId: ?int}>
	 */
	public function replaceEntries(string $userId, int $structureId, array $entries): array {
		$structure = $this->getOwned($userId, $structureId);
		/** @var array<int, Chapter> $chapterById */
		$chapterById = [];
		foreach ($this->chapterMapper->findByBook($structure->getBookId()) as $chapter) {
			$chapterById[$chapter->getId()] = $chapter;
		}

		$normalized = [];
		$seen = [];
		foreach ($entries as $entry) {
			if (!is_array($entry)) {
				throw new ValidationException('Structure entries must be objects');
			}

			$chapterId = filter_var($entry['chapterId'] ?? null, FILTER_VALIDATE_INT);
			if ($chapterId === false || $chapterId <= 0) {
				throw new ValidationException('A valid chapter id is required');
			}
			$chapter = $chapterById[$chapterId] ?? null;
			if ($chapter === null) {
				throw new ValidationException('Chapter does not belong to this book');
			}
			if (isset($seen[$chapterId])) {
				throw new ValidationException('Each chapter may appear only once');
			}
			$seen[$chapterId] = true;

			if (!array_key_exists('included', $entry) || !is_bool($entry['included'])) {
				throw new ValidationException('The included flag is required');
			}
			if ($structure->getIsDefault() && $entry['included'] === false) {
				throw new ValidationException('The default structure must include every chapter');
			}

			$position = filter_var($entry['position'] ?? null, FILTER_VALIDATE_INT);
			if ($position === false || $position < 0) {
				throw new ValidationException('A valid position is required');
			}

			$normalized[] = [
				'chapterId' => $chapterId,
				'included' => $entry['included'],
				'position' => $position,
				'parentId' => $chapter->getParentId(),
			];
		}

		if (count($normalized) !== count($chapterById)) {
			throw new ValidationException('Every chapter must appear exactly once');
		}

		$groups = [];
		foreach ($normalized as $index => $entry) {
			$parentId = $entry['parentId'] ?? 0;
			$groups[$parentId][] = $index;
		}
		foreach ($groups as $indexes) {
			usort($indexes, static function (int $a, int $b) use ($normalized): int {
				return $normalized[$a]['position'] <=> $normalized[$b]['position']
					?: $normalized[$a]['chapterId'] <=> $normalized[$b]['chapterId'];
			});
			foreach ($indexes as $position => $index) {
				$normalized[$index]['position'] = $position + 1;
			}
		}

		$this->db->beginTransaction();
		try {
			$this->structureChapterMapper->deleteByStructure($structureId);
			foreach ($normalized as $entry) {
				$this->structureChapterMapper->insertEntry(
					$structureId,
					$entry['chapterId'],
					$entry['included'],
					$entry['position'],
				);
			}

			$structure->setUpdatedAt(new DateTime());
			$this->structureMapper->update($structure);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $this->getEntries($userId, $structureId);
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Structure name is required');
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Structure name is too long');
		}

		return $name;
	}

	private function assertBookOwned(string $userId, int $bookId): void {
		if ($this->bookMapper->findByIdAndUser($bookId, $userId) === null) {
			throw new NotFoundException('Book not found');
		}
	}
}
