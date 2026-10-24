<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Initial BookTailor domain schema.
 *
 * @psalm-suppress UnusedClass
 */
class Version1000Date20261024 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('booktailor_books')) {
			$table = $schema->createTable('booktailor_books');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('root_file_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'booktailor_books_user_idx');
			$table->addUniqueIndex(['root_file_id'], 'booktailor_books_root_file_uniq');
		}

		if (!$schema->hasTable('booktailor_chapters')) {
			$table = $schema->createTable('booktailor_chapters');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('parent_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['book_id', 'parent_id'], 'booktailor_chapters_book_parent_idx');
			$table->addUniqueIndex(['file_id'], 'booktailor_chapters_file_uniq');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_chapters_book_fk');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_chapters')->getName(), ['parent_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_chapters_parent_fk');
		}

		if (!$schema->hasTable('booktailor_structures')) {
			$table = $schema->createTable('booktailor_structures');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('is_default', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['book_id'], 'booktailor_structures_book_idx');
			$table->addUniqueIndex(['book_id', 'name'], 'booktailor_structures_book_name_uniq');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_structures_book_fk');
		}

		if (!$schema->hasTable('booktailor_structure_chapters')) {
			$table = $schema->createTable('booktailor_structure_chapters');
			$table->addColumn('structure_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('chapter_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('included', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$table->addColumn('position', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['structure_id', 'chapter_id']);
			$table->addIndex(['chapter_id'], 'booktailor_structure_chapters_chapter_idx');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_structures')->getName(), ['structure_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_structure_chapters_structure_fk');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_chapters')->getName(), ['chapter_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_structure_chapters_chapter_fk');
		}

		if (!$schema->hasTable('booktailor_assets')) {
			$table = $schema->createTable('booktailor_assets');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('chapter_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('file_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('file_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['chapter_id'], 'booktailor_assets_chapter_idx');
			$table->addUniqueIndex(['chapter_id', 'name'], 'booktailor_assets_chapter_name_uniq');
			$table->addUniqueIndex(['chapter_id', 'file_name'], 'booktailor_assets_chapter_file_uniq');
			$table->addUniqueIndex(['file_id'], 'booktailor_assets_file_uniq');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_chapters')->getName(), ['chapter_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_assets_chapter_fk');
		}

		if (!$schema->hasTable('booktailor_tags')) {
			$table = $schema->createTable('booktailor_tags');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('name', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('slug', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['book_id', 'slug'], 'booktailor_tags_book_slug_uniq');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_tags_book_fk');
		}

		if (!$schema->hasTable('booktailor_asset_tags')) {
			$table = $schema->createTable('booktailor_asset_tags');
			$table->addColumn('asset_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('tag_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->setPrimaryKey(['asset_id', 'tag_id']);
			$table->addIndex(['tag_id'], 'booktailor_asset_tags_tag_idx');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_assets')->getName(), ['asset_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_asset_tags_asset_fk');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_tags')->getName(), ['tag_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_asset_tags_tag_fk');
		}

		if (!$schema->hasTable('booktailor_compositions')) {
			$table = $schema->createTable('booktailor_compositions');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('structure_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('structure_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'queued']);
			$table->addColumn('output_file_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('output_path', Types::STRING, ['length' => 4000, 'notnull' => false]);
			$table->addColumn('error', Types::TEXT, ['notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('started_at', Types::DATETIME, ['notnull' => false]);
			$table->addColumn('completed_at', Types::DATETIME, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['book_id', 'created_at'], 'booktailor_compositions_book_idx');
			$table->addIndex(['status'], 'booktailor_compositions_status_idx');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_compositions_book_fk');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_structures')->getName(), ['structure_id'], ['id'], ['onDelete' => 'SET NULL'], 'booktailor_compositions_structure_fk');
		}

		if (!$schema->hasTable('booktailor_composition_rules')) {
			$table = $schema->createTable('booktailor_composition_rules');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('composition_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('tag_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('priority', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('fallback', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['composition_id', 'priority', 'tag_id'], 'booktailor_composition_rules_unique');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_compositions')->getName(), ['composition_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_composition_rules_composition_fk');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_tags')->getName(), ['tag_id'], ['id'], ['onDelete' => 'CASCADE'], 'booktailor_composition_rules_tag_fk');
		}

		if (!$schema->hasTable('booktailor_ai_action_log')) {
			$table = $schema->createTable('booktailor_ai_action_log');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('user_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => false, 'length' => 20]);
			$table->addColumn('action', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('payload', Types::TEXT, ['notnull' => true]);
			$table->addColumn('status', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'proposed']);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['book_id', 'created_at'], 'booktailor_ai_action_book_idx');
			$table->addIndex(['user_id', 'created_at'], 'booktailor_ai_action_user_idx');
			$table->addForeignKeyConstraint($schema->getTable('booktailor_books')->getName(), ['book_id'], ['id'], ['onDelete' => 'SET NULL'], 'booktailor_ai_action_book_fk');
		}

		return $schema;
	}
}
