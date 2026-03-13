<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldValueService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldValueServiceTest extends TestCase {
	private FieldValueMapper&MockObject $fieldValueMapper;
	private FieldValueService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->service = new FieldValueService($this->fieldValueMapper);
	}

	public function testNormalizeNumberValue(): void {
		$definition = $this->buildDefinition(FieldType::NUMBER->value);

		$normalized = $this->service->normalizeValue($definition, '42');

		$this->assertSame(['value' => 42], $normalized);
	}

	public function testNormalizeMissingValueAsNull(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);

		$this->assertSame(['value' => null], $this->service->normalizeValue($definition, null));
	}

	public function testUpsertPersistsSerializedValue(): void {
		$definition = $this->buildDefinition(FieldType::NUMBER->value);
		$definition->setId(3);
		$definition->setInitialVisibility('users');

		$this->fieldValueMapper
			->method('findByFieldDefinitionIdAndUserUid')
			->with(3, 'alice')
			->willReturn(null);

		$this->fieldValueMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (FieldValue $value): bool {
				$this->assertSame(3, $value->getFieldDefinitionId());
				$this->assertSame('alice', $value->getUserUid());
				$this->assertSame('{"value":9.5}', $value->getValueJson());
				$this->assertSame('users', $value->getCurrentVisibility());
				$this->assertSame('admin', $value->getUpdatedByUid());
				$this->assertInstanceOf(\DateTimeInterface::class, $value->getUpdatedAt());
				return true;
			}))
			->willReturnCallback(static fn (FieldValue $value): FieldValue => $value);

		$stored = $this->service->upsert($definition, 'alice', '9.5', 'admin');

		$this->assertSame('{"value":9.5}', $stored->getValueJson());
	}

	public function testSerializeForResponseReturnsDecodedPayload(): void {
		$value = new FieldValue();
		$value->setId(10);
		$value->setFieldDefinitionId(3);
		$value->setUserUid('alice');
		$value->setValueJson('{"value":42}');
		$value->setCurrentVisibility('users');
		$value->setUpdatedByUid('admin');
		$value->setUpdatedAt(new \DateTime());

		$serialized = $this->service->serializeForResponse($value);

		$this->assertSame(10, $serialized['id']);
		$this->assertSame(3, $serialized['field_definition_id']);
		$this->assertSame('alice', $serialized['user_uid']);
		$this->assertSame(['value' => 42], $serialized['value']);
		$this->assertSame('users', $serialized['current_visibility']);
		$this->assertSame('admin', $serialized['updated_by_uid']);
		$this->assertIsString($serialized['updated_at']);
	}

	public function testSerializeForResponseRejectsInvalidJson(): void {
		$value = new FieldValue();
		$value->setId(10);
		$value->setFieldDefinitionId(3);
		$value->setUserUid('alice');
		$value->setValueJson('not-json');
		$value->setCurrentVisibility('users');
		$value->setUpdatedByUid('admin');
		$value->setUpdatedAt(new \DateTime());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('value_json could not be decoded');

		$this->service->serializeForResponse($value);
	}

	public function testUpdateVisibilityUpdatesExistingValue(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);
		$value = new FieldValue();
		$value->setId(10);
		$value->setFieldDefinitionId(3);
		$value->setUserUid('alice');
		$value->setValueJson('{"value":"abc"}');
		$value->setCurrentVisibility('private');
		$value->setUpdatedByUid('alice');
		$value->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(3, 'alice')
			->willReturn($value);
		$this->fieldValueMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (FieldValue $updated): bool {
				$this->assertSame('users', $updated->getCurrentVisibility());
				$this->assertSame('admin', $updated->getUpdatedByUid());
				$this->assertInstanceOf(\DateTimeInterface::class, $updated->getUpdatedAt());
				return true;
			}))
			->willReturnCallback(static fn (FieldValue $updated): FieldValue => $updated);

		$updated = $this->service->updateVisibility($definition, 'alice', 'admin', 'users');

		$this->assertSame('users', $updated->getCurrentVisibility());
	}

	public function testUpdateVisibilityRejectsMissingValue(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);

		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(3, 'alice')
			->willReturn(null);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('field value not found');

		$this->service->updateVisibility($definition, 'alice', 'admin', 'users');
	}

	private function buildDefinition(string $type): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setType($type);
		$definition->setInitialVisibility('private');
		return $definition;
	}
}
