<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * AI provider profiles, user prompts, and audit decision metadata.
 *
 * @psalm-suppress UnusedClass
 */
class Version1003Date20261027 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('booktailor_llm_profiles')) {
			$table = $schema->createTable('booktailor_llm_profiles');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('provider', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('api_key', Types::TEXT, ['notnull' => true]);
			$table->addColumn('model', Types::STRING, ['length' => 128, 'notnull' => false]);
			$table->addColumn('connect_timeout', Types::INTEGER, ['notnull' => true, 'default' => 30]);
			$table->addColumn('read_timeout', Types::INTEGER, ['notnull' => true, 'default' => 60]);
			$table->addColumn('max_tokens', Types::INTEGER, ['notnull' => true, 'default' => 2048]);
			$table->addColumn('is_active', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'booktailor_llm_profiles_user_idx');
		}

		if (!$schema->hasTable('booktailor_ai_prompts')) {
			$table = $schema->createTable('booktailor_ai_prompts');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('text', Types::TEXT, ['notnull' => true]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['book_id'], 'booktailor_ai_prompts_book_idx');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_ai_prompts_book_fk');
		}

		if ($schema->hasTable('booktailor_ai_action_log')) {
			$table = $schema->getTable('booktailor_ai_action_log');
			if (!$table->hasColumn('decided_at')) {
				$table->addColumn('decided_at', Types::DATETIME, ['notnull' => false]);
			}
			if (!$table->hasColumn('result')) {
				$table->addColumn('result', Types::TEXT, ['notnull' => false]);
			}
		}

		return $schema;
	}
}
