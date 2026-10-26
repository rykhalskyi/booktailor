<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCA\BookTailor\Entity\AiPrompt;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<AiPrompt>
 */
class AiPromptMapper extends QBMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'booktailor_ai_prompts', AiPrompt::class);
	}

	/**
	 * @return list<AiPrompt>
	 */
	public function findByBook(int $bookId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->tableName)
			->where($qb->expr()->eq('book_id', $qb->createNamedParameter($bookId, IQueryBuilder::PARAM_INT)))
			->orderBy('name', 'ASC');

		return $this->findEntities($qb);
	}

	public function findById(int $id): ?AiPrompt {
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
}
