<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use DateTime;
use OCA\BookTailor\Entity\Composition;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<Composition>
 */
class CompositionMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'booktailor_compositions', Composition::class);
	}

	public function findById(int $id): ?Composition {
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

	/**
	 * @return list<Composition>
	 */
	public function findByBook(int $bookId, ?string $status = null, ?int $limit = null, int $offset = 0): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->orderBy('created_at', 'DESC')
			->addOrderBy('id', 'DESC');

		if ($status !== null) {
			$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_STR)));
		}
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		if ($offset > 0) {
			$qb->setFirstResult($offset);
		}

		return $this->findEntities($qb);
	}

	public function claim(int $id, DateTime $startedAt): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(Composition::STATUS_RUNNING))
			->set('started_at', $qb->createNamedParameter($startedAt, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter(Composition::STATUS_QUEUED)));

		return $qb->executeStatement() === 1;
	}

	public function markSucceeded(int $id, int $outputFileId, string $outputPath, DateTime $completedAt): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(Composition::STATUS_SUCCEEDED))
			->set('output_file_id', $qb->createNamedParameter($outputFileId, IQueryBuilder::PARAM_INT))
			->set('output_path', $qb->createNamedParameter($outputPath, IQueryBuilder::PARAM_STR))
			->set('error', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR))
			->set('completed_at', $qb->createNamedParameter($completedAt, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function markFailed(int $id, string $error, DateTime $completedAt): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(Composition::STATUS_FAILED))
			->set('error', $qb->createNamedParameter($error, IQueryBuilder::PARAM_STR))
			->set('output_file_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT))
			->set('output_path', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR))
			->set('completed_at', $qb->createNamedParameter($completedAt, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function resetForRetry(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->tableName)
			->set('status', $qb->createNamedParameter(Composition::STATUS_QUEUED))
			->set('error', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR))
			->set('output_file_id', $qb->createNamedParameter(null, IQueryBuilder::PARAM_INT))
			->set('output_path', $qb->createNamedParameter(null, IQueryBuilder::PARAM_STR))
			->set('started_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->set('completed_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
