<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add the optional composition output-name hint.
 *
 * @psalm-suppress UnusedClass
 */
class Version1002Date20261026 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('booktailor_compositions')) {
			return null;
		}

		$table = $schema->getTable('booktailor_compositions');
		if (!$table->hasColumn('output_name')) {
			$table->addColumn('output_name', Types::STRING, [
				'length' => 255,
				'notnull' => false,
			]);
		}

		return $schema;
	}
}
