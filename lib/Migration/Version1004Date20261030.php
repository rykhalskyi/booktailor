<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Remove stored AI action history and keep proposals pending-only.
 *
 * @psalm-suppress UnusedClass
 */
class Version1004Date20261030 extends SimpleMigrationStep {
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('booktailor_ai_action_log')) {
			return null;
		}

		$table = $schema->getTable('booktailor_ai_action_log');
		if ($table->hasColumn('decided_at')) {
			$table->dropColumn('decided_at');
		}
		if ($table->hasColumn('result')) {
			$table->dropColumn('result');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		if (!$this->db->tableExists('booktailor_ai_action_log')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('booktailor_ai_action_log');
		$qb->executeStatement();
	}
}
