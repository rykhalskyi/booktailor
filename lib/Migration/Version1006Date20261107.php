<?php

declare(strict_types=1);

namespace OCA\BookTailor\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Add whole-book shares.
 *
 * @psalm-suppress UnusedClass
 */
class Version1006Date20261107 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();

		if (!$schema->hasTable('booktailor_book_shares')) {
			$table = $schema->createTable('booktailor_book_shares');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'length' => 20]);
			$table->addColumn('book_id', Types::BIGINT, ['notnull' => true, 'length' => 20]);
			$table->addColumn('shared_with', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('permission', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'read']);
			$table->addColumn('nextcloud_share_id', Types::STRING, ['length' => 255, 'notnull' => false]);
			$table->addColumn('created_at', Types::DATETIME, ['notnull' => true]);
			$table->addColumn('updated_at', Types::DATETIME, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['book_id', 'shared_with'], 'bt_book_share_book_user_uniq');
			$table->addIndex(['shared_with'], 'bt_book_share_user_idx');
			$table->addIndex(['nextcloud_share_id'], 'bt_book_share_nc_idx');
			$table->addForeignKeyConstraint(
				$schema->getTable('booktailor_books')->getName(),
				['book_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'bt_book_share_book_fk',
			);
		}

		return $schema;
	}
}
