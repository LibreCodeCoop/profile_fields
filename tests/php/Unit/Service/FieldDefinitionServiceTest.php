<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldDefinitionValidator;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionServiceTest extends TestCase {
	private FieldDefinitionMapper&MockObject $fieldDefinitionMapper;
	private FieldValueMapper&MockObject $fieldValueMapper;
	private IL10N&MockObject $l10n;
	private FieldDefinitionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldDefinitionMapper = $this->createMock(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters),
		);
		$this->service = new FieldDefinitionService(
			$this->fieldDefinitionMapper,
			$this->fieldValueMapper,
			new FieldDefinitionValidator(),
			$this->l10n,
		);
	}

	public function testCreateRejectsDuplicatedFieldKey(): void {
		$this->fieldDefinitionMapper
			->method('findByFieldKey')
			->with('cpf')
			->willReturn(new FieldDefinition());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"field_key" already exists');

		$this->service->create([
			'field_key' => 'cpf',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
		]);
	}

	public function testCreatePersistsValidatedDefinition(): void {
		$this->fieldDefinitionMapper
			->method('findByFieldKey')
			->willReturn(null);

		$this->fieldDefinitionMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (FieldDefinition $definition): bool {
				$this->assertSame('performance_score', $definition->getFieldKey());
				$this->assertSame('Performance score', $definition->getLabel());
				$this->assertSame(FieldType::NUMBER->value, $definition->getType());
				$this->assertSame(FieldExposurePolicy::USERS->value, $definition->getExposurePolicy());
				$this->assertSame(FieldEditPolicy::ADMINS->value, $definition->getEditPolicy());
				$this->assertInstanceOf(\DateTimeInterface::class, $definition->getCreatedAt());
				$this->assertInstanceOf(\DateTimeInterface::class, $definition->getUpdatedAt());
				return true;
			}))
			->willReturnCallback(static fn (FieldDefinition $definition): FieldDefinition => $definition);

		$created = $this->service->create([
			'field_key' => 'performance_score',
			'label' => 'Performance score',
			'type' => FieldType::NUMBER->value,
			'exposure_policy' => FieldExposurePolicy::USERS->value,
			'edit_policy' => FieldEditPolicy::ADMINS->value,
		]);

		$this->assertSame('performance_score', $created->getFieldKey());
	}

	public function testCreatePreservesImportedTimestamps(): void {
		$this->fieldDefinitionMapper
			->method('findByFieldKey')
			->willReturn(null);

		$this->fieldDefinitionMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (FieldDefinition $definition): bool {
				$this->assertSame('2026-03-10T08:00:00+00:00', $definition->getCreatedAt()->format(DATE_ATOM));
				$this->assertSame('2026-03-11T09:30:00+00:00', $definition->getUpdatedAt()->format(DATE_ATOM));
				return true;
			}))
			->willReturnCallback(static fn (FieldDefinition $definition): FieldDefinition => $definition);

		$created = $this->service->create([
			'field_key' => 'region',
			'label' => 'Region',
			'type' => FieldType::TEXT->value,
			'created_at' => '2026-03-10T08:00:00+00:00',
			'updated_at' => '2026-03-11T09:30:00+00:00',
		]);

		$this->assertSame('2026-03-10T08:00:00+00:00', $created->getCreatedAt()->format(DATE_ATOM));
		$this->assertSame('2026-03-11T09:30:00+00:00', $created->getUpdatedAt()->format(DATE_ATOM));
	}

	public function testUpdateRejectsFieldKeyRename(): void {
		$existing = new FieldDefinition();
		$existing->setId(7);
		$existing->setFieldKey('cpf');
		$existing->setLabel('CPF');
		$existing->setType(FieldType::TEXT->value);
		$existing->setExposurePolicy(FieldExposurePolicy::PRIVATE->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"field_key" cannot be changed');

		$this->service->update($existing, [
			'field_key' => 'cpf_new',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
		]);
	}

	public function testUpdateRejectsTypeChangeWhenValuesExist(): void {
		$existing = new FieldDefinition();
		$existing->setId(7);
		$existing->setFieldKey('cpf');
		$existing->setLabel('CPF');
		$existing->setType(FieldType::TEXT->value);
		$existing->setExposurePolicy(FieldExposurePolicy::PRIVATE->value);

		$this->fieldValueMapper
			->method('hasValuesForFieldDefinitionId')
			->with(7)
			->willReturn(true);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Type cannot be changed after values exist');

		$this->service->update($existing, [
			'label' => 'CPF',
			'type' => FieldType::NUMBER->value,
		]);
	}

	public function testCreateSelectFieldPersistsOptions(): void {
		$this->fieldDefinitionMapper
			->method('findByFieldKey')
			->willReturn(null);

		$this->fieldDefinitionMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (FieldDefinition $definition): bool {
				$this->assertSame(FieldType::SELECT->value, $definition->getType());
				$this->assertSame('["CLT","PJ","Cooperado"]', $definition->getOptions());
				return true;
			}))
			->willReturnCallback(static fn (FieldDefinition $definition): FieldDefinition => $definition);

		$this->service->create([
			'field_key' => 'contract_type',
			'label' => 'Contract Type',
			'type' => FieldType::SELECT->value,
			'options' => ['CLT', 'PJ', 'Cooperado'],
		]);
	}

	public function testJsonSerializeSelectIncludesOptions(): void {
		$definition = new FieldDefinition();
		$definition->setId(1);
		$definition->setFieldKey('contract_type');
		$definition->setLabel('Contract Type');
		$definition->setType(FieldType::SELECT->value);
		$definition->setEditPolicy(FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(FieldExposurePolicy::PRIVATE->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setOptions('["CLT","PJ"]');
		$definition->setCreatedAt(new \DateTime('2026-01-01T00:00:00+00:00'));
		$definition->setUpdatedAt(new \DateTime('2026-01-01T00:00:00+00:00'));

		$serialized = $definition->jsonSerialize();

		$this->assertSame(['CLT', 'PJ'], $serialized['options']);
	}

	public function testJsonSerializeTextHasNullOptions(): void {
		$definition = new FieldDefinition();
		$definition->setId(1);
		$definition->setFieldKey('cpf');
		$definition->setLabel('CPF');
		$definition->setType(FieldType::TEXT->value);
		$definition->setEditPolicy(FieldEditPolicy::ADMINS->value);
		$definition->setExposurePolicy(FieldExposurePolicy::HIDDEN->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime('2026-01-01T00:00:00+00:00'));
		$definition->setUpdatedAt(new \DateTime('2026-01-01T00:00:00+00:00'));

		$serialized = $definition->jsonSerialize();

		$this->assertNull($serialized['options']);
	}

	public function testUpdatePreservesImportedUpdatedAt(): void {
		$existing = new FieldDefinition();
		$existing->setId(7);
		$existing->setFieldKey('cpf');
		$existing->setLabel('CPF');
		$existing->setType(FieldType::TEXT->value);
		$existing->setEditPolicy(FieldEditPolicy::ADMINS->value);
		$existing->setExposurePolicy(FieldExposurePolicy::PRIVATE->value);
		$existing->setSortOrder(0);
		$existing->setActive(true);
		$existing->setCreatedAt(new \DateTime('2026-03-01T00:00:00+00:00'));
		$existing->setUpdatedAt(new \DateTime('2026-03-02T00:00:00+00:00'));

		$this->fieldDefinitionMapper
			->expects($this->once())
			->method('update')
			->with($this->callback(function (FieldDefinition $definition): bool {
				$this->assertSame('2026-03-12T10:00:00+00:00', $definition->getUpdatedAt()->format(DATE_ATOM));
				return true;
			}))
			->willReturnCallback(static fn (FieldDefinition $definition): FieldDefinition => $definition);

		$updated = $this->service->update($existing, [
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
			'updated_at' => '2026-03-12T10:00:00+00:00',
		]);

		$this->assertSame('2026-03-12T10:00:00+00:00', $updated->getUpdatedAt()->format(DATE_ATOM));
	}
}
