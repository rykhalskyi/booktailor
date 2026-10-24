<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCA\BookTailor\Entity\Tag;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Tag>
 */
class TagMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'booktailor_tags', Tag::class);
	}

	/**
	 * @return list<Tag>
	 */
	public function findByBook(int $bookId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findById(int $id): ?Tag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findByBookAndSlug(int $bookId, string $slug): ?Tag {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('slug', $qb->createNamedParameter($slug, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param list<int> $ids
	 * @return list<Tag>
	 */
	public function findByIdsAndBook(array $ids, int $bookId): array {
		if ($ids === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}
}
