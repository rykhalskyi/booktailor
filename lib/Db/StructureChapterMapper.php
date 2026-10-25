<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class StructureChapterMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function insertEntry(int $structureId, int $chapterId, bool $included, int $position): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('booktailor_structure_chapters')
			->values([
				'structure_id' => $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT),
				'chapter_id' => $qb->createNamedParameter($chapterId, IQueryBuilder::PARAM_INT),
				'included' => $qb->createNamedParameter($included, IQueryBuilder::PARAM_BOOL),
				'position' => $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	public function nextPosition(int $structureId, int $bookId, ?int $parentId): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->max('sc.position'))
			->from('booktailor_structure_chapters', 'sc')
			->innerJoin('sc', 'booktailor_chapters', 'c', 'sc.chapter_id = c.id')
			->where($qb->expr()->eq('sc.structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('c.book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)));

		if ($parentId === null) {
			$qb->andWhere($qb->expr()->isNull('c.parent_id'));
		} else {
			$qb->andWhere($qb->expr()->eq('c.parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)));
		}

		$max = $qb->executeQuery()->fetchOne();
		if (!is_int($max) && !is_string($max)) {
			return 1;
		}

		return (int)$max + 1;
	}

	public function updatePosition(int $structureId, int $chapterId, int $position): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('booktailor_structure_chapters')
			->set('position', $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('chapter_id', $qb->createNamedParameter($chapterId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteByStructure(int $structureId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('booktailor_structure_chapters')
			->where($qb->expr()->eq('structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @return list<array{chapter_id: int, included: bool, position: int}>
	 */
	public function findByStructure(int $structureId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('chapter_id', 'included', 'position')
			->from('booktailor_structure_chapters')
			->where($qb->expr()->eq('structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)))
			->orderBy('position', 'ASC')
			->addOrderBy('chapter_id', 'ASC');

		/** @var list<array{chapter_id: int|string, included: bool|int|string, position: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();

		return array_map(static fn (array $row): array => [
			'chapter_id' => (int)$row['chapter_id'],
			'included' => (bool)$row['included'],
			'position' => (int)$row['position'],
		], $rows);
	}
}
