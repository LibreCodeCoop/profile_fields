<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;

/**
 * Builds an ISchemaWrapper backed by a real Doctrine schema.
 *
 * Nextcloud 35 typed ISchemaWrapper and moved migrations from Doctrine objects to ITable and
 * IColumn, while 32 to 34 still hand out the Doctrine ones. No single hand written class can
 * implement both contracts, so the wrapper is mocked from whichever interface is installed and
 * every call is delegated to a real Doctrine schema, keeping the names it generates observable.
 */
trait BuildsSchemaWrapper {
	/** @var array<string, object> */
	private array $wrappedTables = [];

	/** @var array<int, Table> */
	private array $doctrineTables = [];

	private function usesTypedSchemaApi(): bool {
		return interface_exists(ITable::class);
	}

	private function buildSchemaWrapper(Schema $schema, string $prefix): ISchemaWrapper {
		$wrapper = $this->createMock(ISchemaWrapper::class);
		$wrapper->method('hasTable')
			->willReturnCallback(fn (string $tableName): bool => $schema->hasTable($prefix . $tableName));
		$wrapper->method('createTable')
			->willReturnCallback(fn (string $tableName) => $this->wrapTable($schema->createTable($prefix . $tableName)));
		$wrapper->method('getTable')
			->willReturnCallback(fn (string $tableName) => $this->wrapTable($schema->getTable($prefix . $tableName)));

		return $wrapper;
	}

	private function wrapTable(Table $table): mixed {
		if (!$this->usesTypedSchemaApi()) {
			return $table;
		}

		$tableName = $table->getName();
		if (isset($this->wrappedTables[$tableName])) {
			return $this->wrappedTables[$tableName];
		}

		$wrapped = $this->createMock(ITable::class);
		$this->wrappedTables[$tableName] = $wrapped;
		$this->doctrineTables[spl_object_id($wrapped)] = $table;

		$wrapped->method('getName')
			->willReturnCallback(fn (): string => $table->getName());
		$wrapped->method('hasColumn')
			->willReturnCallback(fn (string $name): bool => $table->hasColumn($name));
		$wrapped->method('getColumn')
			->willReturnCallback(fn (string $name) => $this->wrapColumn($table->getColumn($name)));
		$wrapped->method('addColumn')
			->willReturnCallback(function (string $name, $typeName, array $options = []) use ($table) {
				return $this->wrapColumn($table->addColumn($name, is_string($typeName) ? $typeName : $typeName->value, $options));
			});
		$wrapped->method('setPrimaryKey')
			->willReturnCallback(function (array $columnNames, string|false $indexName = false) use ($table, $wrapped) {
				$table->setPrimaryKey($columnNames, $indexName);

				return $wrapped;
			});
		$wrapped->method('addIndex')
			->willReturnCallback(function (array $columnNames, ?string $indexName = null, array $flags = [], array $options = []) use ($table, $wrapped) {
				$table->addIndex($columnNames, $indexName, $flags, $options);

				return $wrapped;
			});
		$wrapped->method('addUniqueIndex')
			->willReturnCallback(function (array $columnNames, ?string $indexName = null, array $options = []) use ($table, $wrapped) {
				$table->addUniqueIndex($columnNames, $indexName, $options);

				return $wrapped;
			});
		$wrapped->method('addForeignKeyConstraint')
			->willReturnCallback(function ($foreignTable, array $localColumnNames, array $foreignColumnNames, array $options = [], ?string $name = null) use ($table, $wrapped) {
				$table->addForeignKeyConstraint(
					is_object($foreignTable) ? ($this->doctrineTables[spl_object_id($foreignTable)] ?? $foreignTable) : $foreignTable,
					$localColumnNames,
					$foreignColumnNames,
					$options,
					$name,
				);

				return $wrapped;
			});

		return $wrapped;
	}

	private function wrapColumn(Column $column): mixed {
		if (!$this->usesTypedSchemaApi()) {
			return $column;
		}

		$wrapped = $this->createMock(IColumn::class);
		$wrapped->method('getName')
			->willReturnCallback(fn (): string => $column->getName());
		$wrapped->method('getNotnull')
			->willReturnCallback(fn (): bool => $column->getNotnull());
		$wrapped->method('getDefault')
			->willReturnCallback(fn () => $column->getDefault());
		$wrapped->method('setNotnull')
			->willReturnCallback(function (bool $notnull) use ($column, $wrapped) {
				$column->setNotnull($notnull);

				return $wrapped;
			});
		$wrapped->method('setDefault')
			->willReturnCallback(function ($default) use ($column, $wrapped) {
				$column->setDefault($default);

				return $wrapped;
			});

		return $wrapped;
	}
}
