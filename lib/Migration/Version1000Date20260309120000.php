<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260309120000 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array $options
	 */
	#[\Override]
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('profile_fields_definitions')) {
			$table = $schema->createTable('profile_fields_definitions');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'unsigned' => true,
			]);
			$table->addColumn('field_key', Types::STRING, [
				'length' => 64,
			]);
			$table->addColumn('label', Types::STRING, [
				'length' => 255,
			]);
			$table->addColumn('type', Types::STRING, [
				'length' => 32,
			]);
			$table->addColumn('admin_only', Types::BOOLEAN, [
				'default' => false,
			]);
			$table->addColumn('user_editable', Types::BOOLEAN, [
				'default' => false,
			]);
			$table->addColumn('user_visible', Types::BOOLEAN, [
				'default' => true,
			]);
			$table->addColumn('initial_visibility', Types::STRING, [
				'length' => 32,
				'default' => 'private',
			]);
			$table->addColumn('sort_order', Types::INTEGER, [
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('active', Types::BOOLEAN, [
				'default' => true,
			]);
			$table->addColumn('created_at', Types::DATETIME, []);
			$table->addColumn('updated_at', Types::DATETIME, []);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['field_key'], 'profile_fields_def_key_uk');
			$table->addIndex(['active', 'sort_order'], 'profile_fields_def_active_order_idx');
		}

		if (!$schema->hasTable('profile_fields_values')) {
			$table = $schema->createTable('profile_fields_values');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'unsigned' => true,
			]);
			$table->addColumn('field_definition_id', Types::BIGINT, [
				'unsigned' => true,
			]);
			$table->addColumn('user_uid', Types::STRING, [
				'length' => 64,
			]);
			$table->addColumn('value_json', Types::TEXT, []);
			$table->addColumn('current_visibility', Types::STRING, [
				'length' => 32,
				'default' => 'private',
			]);
			$table->addColumn('updated_by_uid', Types::STRING, [
				'length' => 64,
			]);
			$table->addColumn('updated_at', Types::DATETIME, []);

			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['field_definition_id', 'user_uid'], 'profile_fields_val_field_user_uk');
			$table->addIndex(['user_uid'], 'profile_fields_val_user_idx');
			$table->addIndex(['field_definition_id'], 'profile_fields_val_field_idx');
			$table->addForeignKeyConstraint(
				$schema->getTable('profile_fields_definitions'),
				['field_definition_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'profile_fields_val_field_fk'
			);
		}

		return $schema;
	}
}
