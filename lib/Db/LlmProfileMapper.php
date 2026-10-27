<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCA\BookTailor\Entity\LlmProfile;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<LlmProfile>
 */
class LlmProfileMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'booktailor_llm_profiles', LlmProfile::class);
	}

	/**
	 * @return list<LlmProfile>
	 */
	public function findAllByUser(string $userId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->orderBy('created_at', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	public function findByIdAndUser(int $id, string $userId): ?LlmProfile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function findActiveByUser(string $userId): ?LlmProfile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->eq('is_active', $qb->createNamedParameter(true, IQueryBuilder::PARAM_BOOL)))
			->setMaxResults(1);

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * Activate one profile (deactivating all others) or deactivate all profiles.
	 */
	public function setActive(int $id, string $userId, bool $active): void {
		$this->db->beginTransaction();
		try {
			$qbDeactivate = $this->db->getQueryBuilder();
			$qbDeactivate->update($this->tableName)
				->set('is_active', $qbDeactivate->createNamedParameter(false, IQueryBuilder::PARAM_BOOL))
				->where($qbDeactivate->expr()->eq('user_id', $qbDeactivate->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
			$qbDeactivate->executeStatement();

			if ($active) {
				$qbActivate = $this->db->getQueryBuilder();
				$qbActivate->update($this->tableName)
					->set('is_active', $qbActivate->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
					->set('updated_at', $qbActivate->createNamedParameter(new \DateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE))
					->where($qbActivate->expr()->eq('id', $qbActivate->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->andWhere($qbActivate->expr()->eq('user_id', $qbActivate->createNamedParameter($userId, IQueryBuilder::PARAM_STR)));
				$qbActivate->executeStatement();
			}

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}
}
