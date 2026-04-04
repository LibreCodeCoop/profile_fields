<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class Version1000Date20260309120000Test extends TestCase {
	public function testActiveColumnIsNullableForCrossDatabaseCompatibility(): void {
		$schema = new Schema();
		$schemaWrapper = $this->createMock(ISchemaWrapper::class);

		$schemaWrapper->method('hasTable')
			->willReturnCallback(static fn (string $tableName): bool => $schema->hasTable($tableName));

		$schemaWrapper->method('createTable')
			->willReturnCallback(static fn (string $tableName): \Doctrine\DBAL\Schema\Table => $schema->createTable($tableName));

		$schemaWrapper->method('getTable')
			->willReturnCallback(static fn (string $tableName): \Doctrine\DBAL\Schema\Table => $schema->getTable($tableName));

		$migration = new Version1000Date20260309120000();
		$output = $this->createMock(IOutput::class);

		$migration->changeSchema($output, static fn (): ISchemaWrapper => $schemaWrapper, []);

		$definitionsTable = $schema->getTable('profile_fields_definitions');
		$activeColumn = $definitionsTable->getColumn('active');

		$this->assertFalse($activeColumn->getNotnull());
	}
}
