<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Replace tag-based composition rules with a per-asset inclusion flag.
 *
 * @psalm-suppress UnusedClass
 */
class Version1005Date20261031 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if ($schema->hasTable('booktailor_assets')) {
			$table = $schema->getTable('booktailor_assets');
			if (!$table->hasColumn('include_in_composition')) {
				$table->addColumn('include_in_composition', Types::BOOLEAN, [
					'notnull' => true,
					'default' => false,
				]);
			}
		}

		if ($schema->hasTable('booktailor_composition_rules')) {
			$schema->dropTable('booktailor_composition_rules');
		}

		return $schema;
	}
}
