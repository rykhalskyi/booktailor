<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCA\BookTailor\Entity\CompositionRule;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CompositionRule>
 */
class CompositionRuleMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'booktailor_composition_rules', CompositionRule::class);
	}

	/**
	 * @return list<CompositionRule>
	 */
	public function findByComposition(int $compositionId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('composition_id', $qb->createNamedParameter($compositionId, IQueryBuilder::PARAM_INT)))
			->orderBy('priority', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @param list<int> $compositionIds
	 * @return array<int, list<CompositionRule>>
	 */
	public function findByCompositionIds(array $compositionIds): array {
		if ($compositionIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->in('composition_id', $qb->createNamedParameter($compositionIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('priority', 'ASC')
			->addOrderBy('id', 'ASC');

		$grouped = [];
		foreach ($this->findEntities($qb) as $rule) {
			$grouped[$rule->getCompositionId()][] = $rule;
		}

		return $grouped;
	}

	/**
	 * @param list<array{tagId: int, priority: int, fallback: bool}> $rules
	 */
	public function replaceForComposition(int $compositionId, array $rules): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->tableName)
			->where($qb->expr()->eq('composition_id', $qb->createNamedParameter($compositionId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();

		foreach ($rules as $rule) {
			$entity = new CompositionRule();
			$entity->setCompositionId($compositionId);
			$entity->setTagId($rule['tagId']);
			$entity->setPriority($rule['priority']);
			$entity->setFallback($rule['fallback']);
			$this->insert($entity);
		}
	}
}
