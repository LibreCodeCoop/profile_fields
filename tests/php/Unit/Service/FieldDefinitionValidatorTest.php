<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
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
			'user_editable' => false,
		]);

		$this->assertSame('cpf', $validated['field_key']);
		$this->assertSame(FieldType::TEXT->value, $validated['type']);
		$this->assertSame(FieldVisibility::PRIVATE->value, $validated['initial_visibility']);
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

	public function testNonSelectTypesDoNotRequireOptions(): void {
		$validated = $this->validator->validate([
			'field_key' => 'cpf',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
		]);

		$this->assertNull($validated['options']);
	}

	public function testRejectAdminOnlyAndUserEditableCombination(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('admin_only and user_editable cannot both be enabled');

		$this->validator->validate([
			'field_key' => 'rg',
			'label' => 'RG',
			'type' => FieldType::TEXT->value,
			'admin_only' => true,
			'user_editable' => true,
		]);
	}

	public function testRejectInvalidInitialVisibility(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('initial_visibility is not supported');

		$this->validator->validate([
			'field_key' => 'department',
			'label' => 'Department',
			'type' => FieldType::TEXT->value,
			'initial_visibility' => 'team',
		]);
	}
}
