<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Schema;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Nextcloud Oracle naming constraints for Version1000Date20260309120000.
 *
 * NC 32 ensureOracleConstraints rules (lib/private/DB/MigrationService.php):
 *  - Table names (without prefix) must be <= 27 chars
 *  - Column names must be <= 30 chars
 *  - Index names must be <= 30 chars
 *  - FK names must be <= 30 chars
 *  - Primary key: if default name ('primary'), table name without prefix must be < 23 chars;
 *    otherwise explicit name must be <= 30 chars
 */
class Version1000Date20260309120000Test extends TestCase {
	use BuildsSchemaWrapper;

	private const PREFIX = 'oc_';
	private const ORACLE_MAX_NAME = 30;
	private const ORACLE_MAX_TABLE = 27;
	/** Table name (without prefix) must be < 23 chars to use default PK name */
	private const ORACLE_MAX_TABLE_FOR_DEFAULT_PK = 23;

	private Schema $schema;

	protected function setUp(): void {
		parent::setUp();
		$this->schema = new Schema();
	}

	public function testAllNamesPassOracleConstraints(): void {
		$wrapper = $this->buildSchemaWrapper($this->schema, self::PREFIX);
		$output = $this->createMock(IOutput::class);
		$migration = new Version1000Date20260309120000();

		$migration->changeSchema($output, fn () => $wrapper, ['tablePrefix' => self::PREFIX]);

		$prefixLen = strlen(self::PREFIX);

		foreach ($this->schema->getTables() as $table) {
			$tableNameWithoutPrefix = substr($table->getName(), $prefixLen);

			$this->assertLessThanOrEqual(
				self::ORACLE_MAX_TABLE,
				strlen($tableNameWithoutPrefix),
				"Table '{$table->getName()}' name without prefix exceeds Oracle max of " . self::ORACLE_MAX_TABLE . ' chars',
			);

			foreach ($table->getColumns() as $column) {
				$this->assertLessThanOrEqual(
					self::ORACLE_MAX_NAME,
					strlen($column->getName()),
					"Column '{$column->getName()}' on '{$table->getName()}' exceeds Oracle max of " . self::ORACLE_MAX_NAME . ' chars',
				);
			}

			foreach ($table->getIndexes() as $index) {
				$this->assertLessThanOrEqual(
					self::ORACLE_MAX_NAME,
					strlen($index->getName()),
					"Index '{$index->getName()}' on '{$table->getName()}' exceeds Oracle max of " . self::ORACLE_MAX_NAME . ' chars',
				);
			}

			foreach ($table->getForeignKeys() as $fk) {
				$this->assertLessThanOrEqual(
					self::ORACLE_MAX_NAME,
					strlen($fk->getName()),
					"FK '{$fk->getName()}' on '{$table->getName()}' exceeds Oracle max of " . self::ORACLE_MAX_NAME . ' chars',
				);
			}

			$primaryKey = $table->getPrimaryKey();
			if ($primaryKey !== null) {
				$pkName = strtolower($primaryKey->getName());
				if ($pkName === 'primary') {
					// Default PK name: table name without prefix must be strictly < 23 chars
					$this->assertLessThan(
						self::ORACLE_MAX_TABLE_FOR_DEFAULT_PK,
						strlen($tableNameWithoutPrefix),
						"Table '{$tableNameWithoutPrefix}' uses default primary key name but its length "
						. strlen($tableNameWithoutPrefix)
						. ' >= ' . self::ORACLE_MAX_TABLE_FOR_DEFAULT_PK
						. '; set an explicit primary key name <= 30 chars',
					);
				} else {
					$this->assertLessThanOrEqual(
						self::ORACLE_MAX_NAME,
						strlen($pkName),
						"Primary key name '$pkName' on '{$table->getName()}' exceeds Oracle max of " . self::ORACLE_MAX_NAME . ' chars',
					);
				}
			}
		}
	}
}
