<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCA\BookTailor\Service\TagColor;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add a palette color to tags.
 *
 * @psalm-suppress UnusedClass
 */
class Version1001Date20261025 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		if (!$schema->hasTable('booktailor_tags')) {
			return null;
		}

		$table = $schema->getTable('booktailor_tags');
		if (!$table->hasColumn('color')) {
			$table->addColumn('color', Types::STRING, [
				'length' => 7,
				'notnull' => true,
				'default' => TagColor::DEFAULT,
			]);
		}

		return $schema;
	}
}
