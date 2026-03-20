<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionValidator;
use PHPUnit\Framework\TestCase;

class FieldDefinitionValidatorTest extends TestCase {
	private FieldDefinitionValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new FieldDefinitionValidator();
	}

	public function testValidateTextFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'cpf',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
			'edit_policy' => FieldEditPolicy::ADMINS->value,
		]);

		$this->assertSame('cpf', $validated['field_key']);
		$this->assertSame(FieldType::TEXT->value, $validated['type']);
		$this->assertSame(FieldExposurePolicy::PRIVATE->value, $validated['exposure_policy']);
		$this->assertTrue($validated['active']);
	}

	public function testRejectInvalidType(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('type is not supported');

		$this->validator->validate([
			'field_key' => 'score',
			'label' => 'Score',
			'type' => 'decimal',
		]);
	}

	public function testValidateSelectFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'contract_type',
			'label' => 'Contract Type',
			'type' => FieldType::SELECT->value,
			'options' => ['CLT', 'PJ', 'Cooperado'],
		]);

		$this->assertSame(FieldType::SELECT->value, $validated['type']);
		$this->assertSame(['CLT', 'PJ', 'Cooperado'], $validated['options']);
	}

	public function testRejectSelectWithNoOptions(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('select fields require at least one option');

		$this->validator->validate([
			'field_key' => 'contract_type',
			'label' => 'Contract Type',
			'type' => FieldType::SELECT->value,
			'options' => [],
		]);
	}

	public function testRejectSelectWithMissingOptions(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('select fields require at least one option');

		$this->validator->validate([
			'field_key' => 'contract_type',
			'label' => 'Contract Type',
			'type' => FieldType::SELECT->value,
		]);
	}

	public function testRejectSelectWithBlankOption(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('each option must be a non-empty string');

		$this->validator->validate([
			'field_key' => 'contract_type',
			'label' => 'Contract Type',
			'type' => FieldType::SELECT->value,
			'options' => ['CLT', '  ', 'PJ'],
		]);
	}

	public function testValidateMultiSelectFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'support_regions',
			'label' => 'Support Regions',
			'type' => FieldType::MULTISELECT->value,
			'options' => ['LATAM', 'EMEA', 'APAC'],
		]);

		$this->assertSame(FieldType::MULTISELECT->value, $validated['type']);
		$this->assertSame(['LATAM', 'EMEA', 'APAC'], $validated['options']);
	}

	public function testValidateDateFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'start_date',
			'label' => 'Start Date',
			'type' => FieldType::DATE->value,
		]);

		$this->assertSame(FieldType::DATE->value, $validated['type']);
		$this->assertNull($validated['options']);
	}

	public function testValidateBooleanFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'is_manager',
			'label' => 'Is manager',
			'type' => FieldType::BOOLEAN->value,
		]);

		$this->assertSame(FieldType::BOOLEAN->value, $validated['type']);
		$this->assertNull($validated['options']);
	}

	public function testValidateUrlFieldDefinition(): void {
		$validated = $this->validator->validate([
			'field_key' => 'website',
			'label' => 'Website',
			'type' => FieldType::URL->value,
		]);

		$this->assertSame(FieldType::URL->value, $validated['type']);
		$this->assertNull($validated['options']);
	}

	public function testRejectMultiSelectWithNoOptions(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('multiselect fields require at least one option');

		$this->validator->validate([
			'field_key' => 'support_regions',
			'label' => 'Support Regions',
			'type' => FieldType::MULTISELECT->value,
			'options' => [],
		]);
	}

	public function testNonSelectTypesDoNotRequireOptions(): void {
		$validated = $this->validator->validate([
			'field_key' => 'cpf',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
		]);

		$this->assertNull($validated['options']);
	}

	public function testRejectInvalidEditPolicy(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('edit_policy is not supported');

		$this->validator->validate([
			'field_key' => 'rg',
			'label' => 'RG',
			'type' => FieldType::TEXT->value,
			'edit_policy' => 'mixed',
		]);
	}

	public function testRejectInvalidExposurePolicy(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('exposure_policy is not supported');

		$this->validator->validate([
			'field_key' => 'department',
			'label' => 'Department',
			'type' => FieldType::TEXT->value,
			'exposure_policy' => 'team',
		]);
	}
}
