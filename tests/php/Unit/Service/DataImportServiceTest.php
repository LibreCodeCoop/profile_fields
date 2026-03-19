<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Service\DataImportService;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Service\ImportPayloadValidator;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataImportServiceTest extends TestCase {
	private ImportPayloadValidator&MockObject $importPayloadValidator;
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueService&MockObject $fieldValueService;
	private IDBConnection&MockObject $connection;
	private DataImportService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->importPayloadValidator = $this->createMock(ImportPayloadValidator::class);
		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueService = $this->createMock(FieldValueService::class);
		$this->connection = $this->createMock(IDBConnection::class);
		$this->service = new DataImportService(
			$this->importPayloadValidator,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->connection,
		);
	}

	public function testImportDryRunReturnsSummaryWithoutPersisting(): void {
		$existingDefinition = $this->buildDefinition(7, 'cost_center', 'Cost center', 2, true);
		$existingDefinition->setUpdatedAt(new \DateTime('2026-03-11T09:30:00+00:00'));
		$existingValue = $this->buildValue(7, 'alice', ['value' => 'finance'], 'users', 'ops-admin');
		$existingValue->setUpdatedAt(new \DateTime('2026-03-15T12:00:00+00:00'));

		$this->importPayloadValidator->expects($this->once())
			->method('validate')
			->willReturn($this->buildNormalizedPayload());

		$this->fieldDefinitionService->expects($this->exactly(4))
			->method('findByFieldKey')
			->willReturnMap([
				['region', null],
				['cost_center', $existingDefinition],
				['region', null],
				['cost_center', $existingDefinition],
			]);

		$this->fieldValueService->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($existingValue);

		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($existingValue)
			->willReturn([
				'id' => 1,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'finance'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'ops-admin',
				'updated_at' => '2026-03-15T12:00:00+00:00',
			]);

		$this->connection->expects($this->never())->method('beginTransaction');

		$summary = $this->service->import(['schema_version' => 1], true);

		$this->assertSame([
			'created_definitions' => 1,
			'updated_definitions' => 0,
			'skipped_definitions' => 1,
			'created_values' => 1,
			'updated_values' => 0,
			'skipped_values' => 1,
		], $summary);
	}

	public function testImportPersistsCreatesAndUpdatesInsideTransaction(): void {
		$existingDefinition = $this->buildDefinition(7, 'cost_center', 'Old cost center', 1, true);
		$updatedDefinition = $this->buildDefinition(7, 'cost_center', 'Cost center', 2, true);
		$createdDefinition = $this->buildDefinition(8, 'region', 'Region', 0, true);
		$existingValue = $this->buildValue(7, 'alice', ['value' => 'legacy'], 'users', 'legacy-admin');

		$this->importPayloadValidator->expects($this->once())
			->method('validate')
			->willReturn($this->buildNormalizedPayload());

		$this->fieldDefinitionService->expects($this->exactly(2))
			->method('findByFieldKey')
			->willReturnMap([
				['region', null],
				['cost_center', $existingDefinition],
			]);

		$this->fieldDefinitionService->expects($this->once())
			->method('create')
			->with($this->callback(static fn (array $definition): bool => $definition['field_key'] === 'region' && $definition['created_at'] === '2026-03-10T08:00:00+00:00' && $definition['updated_at'] === '2026-03-10T08:00:00+00:00'))
			->willReturn($createdDefinition);

		$this->fieldDefinitionService->expects($this->once())
			->method('update')
			->with(
				$existingDefinition,
				$this->callback(static fn (array $definition): bool => $definition['field_key'] === 'cost_center' && $definition['label'] === 'Cost center' && $definition['sort_order'] === 2 && $definition['updated_at'] === '2026-03-11T09:30:00+00:00'),
			)
			->willReturn($updatedDefinition);

		$this->fieldValueService->expects($this->exactly(2))
			->method('findByFieldDefinitionIdAndUserUid')
			->willReturnMap([
				[8, 'bob', null],
				[7, 'alice', $existingValue],
			]);

		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($existingValue)
			->willReturn([
				'id' => 1,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'legacy'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'legacy-admin',
				'updated_at' => '2026-03-15T12:00:00+00:00',
			]);

		$this->fieldValueService->expects($this->exactly(2))
			->method('upsert')
			->with(
				$this->callback(static fn (FieldDefinition $definition): bool => in_array($definition->getFieldKey(), ['region', 'cost_center'], true)),
				$this->callback(static fn (string $userUid): bool => in_array($userUid, ['bob', 'alice'], true)),
				$this->callback(static fn (mixed $value): bool => in_array($value, ['emea', 'finance'], true)),
				$this->callback(static fn (string $updatedByUid): bool => in_array($updatedByUid, ['admin', 'ops-admin'], true)),
				'users',
				$this->callback(static fn (\DateTimeInterface $updatedAt): bool => in_array($updatedAt->format(DATE_ATOM), ['2026-03-15T12:00:00+00:00'], true)),
			);

		$this->connection->expects($this->once())->method('beginTransaction');
		$this->connection->expects($this->once())->method('commit');
		$this->connection->expects($this->never())->method('rollBack');

		$summary = $this->service->import(['schema_version' => 1], false);

		$this->assertSame([
			'created_definitions' => 1,
			'updated_definitions' => 1,
			'skipped_definitions' => 0,
			'created_values' => 1,
			'updated_values' => 1,
			'skipped_values' => 0,
		], $summary);
	}

	/**
	 * @return array{
	 *     schema_version: int,
	 *     definitions: list<array<string, mixed>>,
	 *     values: list<array<string, mixed>>,
	 * }
	 */
	private function buildNormalizedPayload(): array {
		return [
			'schema_version' => 1,
			'definitions' => [
				[
					'field_key' => 'region',
					'label' => 'Region',
					'type' => 'text',
					'edit_policy' => 'admins',
					'exposure_policy' => 'users',
					'sort_order' => 0,
					'active' => true,
					'created_at' => '2026-03-10T08:00:00+00:00',
					'updated_at' => '2026-03-10T08:00:00+00:00',
				],
				[
					'field_key' => 'cost_center',
					'label' => 'Cost center',
					'type' => 'text',
					'edit_policy' => 'admins',
					'exposure_policy' => 'users',
					'sort_order' => 2,
					'active' => true,
					'created_at' => '2026-03-01T12:00:00+00:00',
					'updated_at' => '2026-03-11T09:30:00+00:00',
				],
			],
			'values' => [
				[
					'field_key' => 'region',
					'user_uid' => 'bob',
					'value' => ['value' => 'emea'],
					'current_visibility' => 'users',
					'updated_by_uid' => 'admin',
					'updated_at' => '2026-03-15T12:00:00+00:00',
				],
				[
					'field_key' => 'cost_center',
					'user_uid' => 'alice',
					'value' => ['value' => 'finance'],
					'current_visibility' => 'users',
					'updated_by_uid' => 'ops-admin',
					'updated_at' => '2026-03-15T12:00:00+00:00',
				],
			],
		];
	}

	private function buildDefinition(int $id, string $fieldKey, string $label, int $sortOrder, bool $active): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId($id);
		$definition->setFieldKey($fieldKey);
		$definition->setLabel($label);
		$definition->setType('text');
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::ADMINS->value);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);
		$definition->setSortOrder($sortOrder);
		$definition->setActive($active);
		$definition->setCreatedAt(new \DateTime('2026-03-01T12:00:00+00:00'));
		$definition->setUpdatedAt(new \DateTime('2026-03-02T12:00:00+00:00'));
		return $definition;
	}

	private function buildValue(int $fieldDefinitionId, string $userUid, array $value, string $currentVisibility, string $updatedByUid): FieldValue {
		$fieldValue = new FieldValue();
		$fieldValue->setId(1);
		$fieldValue->setFieldDefinitionId($fieldDefinitionId);
		$fieldValue->setUserUid($userUid);
		$fieldValue->setValueJson(json_encode($value, JSON_THROW_ON_ERROR));
		$fieldValue->setCurrentVisibility($currentVisibility);
		$fieldValue->setUpdatedByUid($updatedByUid);
		$fieldValue->setUpdatedAt(new \DateTime('2026-03-15T12:00:00+00:00'));
		return $fieldValue;
	}
}
