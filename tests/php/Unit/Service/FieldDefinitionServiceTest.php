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
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldDefinitionValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionServiceTest extends TestCase {
	private FieldDefinitionMapper&MockObject $fieldDefinitionMapper;
	private FieldValueMapper&MockObject $fieldValueMapper;
	private FieldDefinitionService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldDefinitionMapper = $this->createMock(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->service = new FieldDefinitionService(
			$this->fieldDefinitionMapper,
			$this->fieldValueMapper,
			new FieldDefinitionValidator(),
		);
	}

	public function testCreateRejectsDuplicatedFieldKey(): void {
		$this->fieldDefinitionMapper
			->method('findByFieldKey')
			->with('cpf')
			->willReturn(new FieldDefinition());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('field_key already exists');

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
				$this->assertSame('users', $definition->getInitialVisibility());
				$this->assertFalse($definition->getUserEditable());
				$this->assertInstanceOf(\DateTimeInterface::class, $definition->getCreatedAt());
				$this->assertInstanceOf(\DateTimeInterface::class, $definition->getUpdatedAt());
				return true;
			}))
			->willReturnCallback(static fn (FieldDefinition $definition): FieldDefinition => $definition);

		$created = $this->service->create([
			'field_key' => 'performance_score',
			'label' => 'Performance score',
			'type' => FieldType::NUMBER->value,
			'initial_visibility' => 'users',
			'user_editable' => false,
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
		$existing->setInitialVisibility('private');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('field_key cannot be changed');

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
		$existing->setInitialVisibility('private');

		$this->fieldValueMapper
			->method('hasValuesForFieldDefinitionId')
			->with(7)
			->willReturn(true);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('type cannot be changed after values exist');

		$this->service->update($existing, [
			'label' => 'CPF',
			'type' => FieldType::NUMBER->value,
		]);
	}

	public function testUpdatePreservesImportedUpdatedAt(): void {
		$existing = new FieldDefinition();
		$existing->setId(7);
		$existing->setFieldKey('cpf');
		$existing->setLabel('CPF');
		$existing->setType(FieldType::TEXT->value);
		$existing->setAdminOnly(false);
		$existing->setUserEditable(false);
		$existing->setUserVisible(true);
		$existing->setInitialVisibility('private');
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
