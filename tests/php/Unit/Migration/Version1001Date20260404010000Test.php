<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCA\ProfileFields\Migration\Version1001Date20260404010000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1001Date20260404010000Test extends TestCase {
	use BuildsSchemaWrapper;

	private const PREFIX = 'oc_';
	private const TABLE = 'profile_fields_definitions';

	private function runMigration(Schema $schema): ?ISchemaWrapper {
		$migration = new Version1001Date20260404010000();

		return $migration->changeSchema(
			$this->createMock(IOutput::class),
			fn () => $this->buildSchemaWrapper($schema, self::PREFIX),
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
