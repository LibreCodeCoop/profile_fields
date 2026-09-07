<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Migration;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use OCA\ProfileFields\Migration\Version1001Date20260404010000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1001Date20260404010000Test extends TestCase {
	private const PREFIX = 'oc_';
	private const TABLE = 'profile_fields_definitions';

	private function buildSchemaWrapper(Schema $schema): ISchemaWrapper {
		return new class($schema, self::PREFIX) implements ISchemaWrapper {
			public function __construct(
				private Schema $schema,
				private string $prefix,
			) {
			}

			public function hasTable($tableName): bool {
				return $this->schema->hasTable($this->prefix . $tableName);
			}

			public function createTable($tableName): Table {
				return $this->schema->createTable($this->prefix . $tableName);
			}

			public function getTable($tableName): Table {
				return $this->schema->getTable($this->prefix . $tableName);
			}

			public function dropTable($tableName): Schema {
				return $this->schema->dropTable($this->prefix . $tableName);
			}

			public function getTables(): array {
				return $this->schema->getTables();
			}

			public function getTableNames(): array {
				return $this->schema->getTableNames();
			}

			public function getTableNamesWithoutPrefix(): array {
				return array_map(
					fn (string $name) => substr($name, strlen($this->prefix)),
					$this->schema->getTableNames(),
				);
			}

			public function getDatabasePlatform(): AbstractPlatform {
				throw new \RuntimeException('not implemented in test');
			}

			public function dropAutoincrementColumn(string $table, string $column): void {
				throw new \RuntimeException('not implemented in test');
			}
		};
	}

	private function runMigration(Schema $schema): ?ISchemaWrapper {
		$wrapper = $this->buildSchemaWrapper($schema);
		$migration = new Version1001Date20260404010000();

		return $migration->changeSchema(
			$this->createMock(IOutput::class),
			fn () => $wrapper,
			['tablePrefix' => self::PREFIX],
		);
	}

	private function schemaWithActiveColumn(bool $notnull): Schema {
		$schema = new Schema();
		$table = $schema->createTable(self::PREFIX . self::TABLE);
		$table->addColumn('active', 'boolean', ['notnull' => $notnull, 'default' => true]);

		return $schema;
	}

	public function testMakesTheActiveColumnNullableKeepingTheDefault(): void {
		$schema = $this->schemaWithActiveColumn(true);

		$this->assertNotNull($this->runMigration($schema));

		$column = $schema->getTable(self::PREFIX . self::TABLE)->getColumn('active');
		$this->assertFalse($column->getNotnull());
		$this->assertTrue($column->getDefault());
	}

	public function testSkipsWhenTheTableIsMissing(): void {
		$this->assertNull($this->runMigration(new Schema()));
	}

	public function testSkipsWhenTheColumnIsMissing(): void {
		$schema = new Schema();
		$schema->createTable(self::PREFIX . self::TABLE)->addColumn('id', 'integer');

		$this->assertNull($this->runMigration($schema));
	}

	public function testSkipsWhenTheColumnIsAlreadyNullable(): void {
		$schema = $this->schemaWithActiveColumn(false);

		$this->assertNull($this->runMigration($schema));
	}
}
