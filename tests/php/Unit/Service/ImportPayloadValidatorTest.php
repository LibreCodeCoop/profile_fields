<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldDefinitionValidator;
use OCA\ProfileFields\Service\ImportPayloadValidator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImportPayloadValidatorTest extends TestCase {
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private IUserManager&MockObject $userManager;
	private ImportPayloadValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->validator = new ImportPayloadValidator(
			new FieldDefinitionValidator(),
			$this->fieldDefinitionService,
			$this->userManager,
		);
	}

	public function testValidateAcceptsVersionedPayload(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('cost_center')
			->willReturn(null);

		$this->userManager->expects($this->once())
			->method('userExists')
			->with('alice')
			->willReturn(true);

		$validated = $this->validator->validate([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => 'cost_center',
				'label' => 'Cost center',
				'type' => FieldType::TEXT->value,
				'edit_policy' => FieldEditPolicy::ADMINS->value,
				'exposure_policy' => FieldExposurePolicy::USERS->value,
				'sort_order' => 1,
				'active' => true,
				'created_at' => '2026-03-10T08:00:00+00:00',
				'updated_at' => '2026-03-11T09:30:00+00:00',
			]],
			'values' => [[
				'field_key' => 'cost_center',
				'user_uid' => 'alice',
				'value' => ['value' => 'finance'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'admin',
				'updated_at' => '2026-03-15T12:00:00+00:00',
			]],
		]);

		$this->assertSame(1, $validated['schema_version']);
		$this->assertSame('cost_center', $validated['definitions'][0]['field_key']);
		$this->assertSame('2026-03-10T08:00:00+00:00', $validated['definitions'][0]['created_at']);
		$this->assertSame('2026-03-11T09:30:00+00:00', $validated['definitions'][0]['updated_at']);
		$this->assertSame(['value' => 'finance'], $validated['values'][0]['value']);
	}

	public function testRejectUnsupportedSchemaVersion(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('schema_version must be 1');

		$this->validator->validate([
			'schema_version' => 2,
			'definitions' => [],
			'values' => [],
		]);
	}

	public function testRejectMissingDestinationUser(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('cost_center')
			->willReturn(null);

		$this->userManager->expects($this->once())
			->method('userExists')
			->with('ghost')
			->willReturn(false);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('values[0].user_uid does not exist in destination instance');

		$this->validator->validate([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => 'cost_center',
				'label' => 'Cost center',
				'type' => FieldType::TEXT->value,
			]],
			'values' => [[
				'field_key' => 'cost_center',
				'user_uid' => 'ghost',
				'value' => ['value' => 'finance'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'admin',
				'updated_at' => '2026-03-15T12:00:00+00:00',
			]],
		]);
	}

	public function testRejectIncompatibleExistingDefinition(): void {
		$existingDefinition = new FieldDefinition();
		$existingDefinition->setId(7);
		$existingDefinition->setFieldKey('cost_center');
		$existingDefinition->setLabel('Cost center');
		$existingDefinition->setType(FieldType::NUMBER->value);
		$existingDefinition->setEditPolicy(FieldEditPolicy::ADMINS->value);
		$existingDefinition->setExposurePolicy(FieldExposurePolicy::USERS->value);

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('cost_center')
			->willReturn($existingDefinition);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('definitions[0].field_key conflicts with an incompatible existing definition');

		$this->validator->validate([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => 'cost_center',
				'label' => 'Cost center',
				'type' => FieldType::TEXT->value,
			]],
			'values' => [],
		]);
	}
}
