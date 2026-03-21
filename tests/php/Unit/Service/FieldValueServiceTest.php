<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueCreatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldVisibilityUpdatedEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldValueServiceTest extends TestCase {
	private FieldValueMapper&MockObject $fieldValueMapper;
	private IEventDispatcher&MockObject $eventDispatcher;
	private IL10N&MockObject $l10n;
	private FieldValueService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->eventDispatcher = $this->createMock(IEventDispatcher::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(
			static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters),
		);
		$this->service = new FieldValueService($this->fieldValueMapper, $this->eventDispatcher, $this->l10n);
	}

	public function testNormalizeNumberValue(): void {
		$definition = $this->buildDefinition(FieldType::NUMBER->value);

		$normalized = $this->service->normalizeValue($definition, '42');

		$this->assertSame(['value' => 42], $normalized);
	}

	public function testNormalizeDateValueAcceptsIsoDate(): void {
		$definition = $this->buildDefinition(FieldType::DATE->value);

		$normalized = $this->service->normalizeValue($definition, '2026-03-20');

		$this->assertSame(['value' => '2026-03-20'], $normalized);
	}

	public function testNormalizeDateValueRejectsInvalidDate(): void {
		$definition = $this->buildDefinition(FieldType::DATE->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Date fields require a valid ISO-8601 date in YYYY-MM-DD format.');

		$this->service->normalizeValue($definition, '2026-02-30');
	}

	public function testNormalizeBooleanValueAcceptsBooleanAndString(): void {
		$definition = $this->buildDefinition(FieldType::BOOLEAN->value);

		$this->assertSame(['value' => true], $this->service->normalizeValue($definition, true));
		$this->assertSame(['value' => false], $this->service->normalizeValue($definition, 'false'));
	}

	public function testNormalizeBooleanValueRejectsInvalidValue(): void {
		$definition = $this->buildDefinition(FieldType::BOOLEAN->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Boolean fields require true or false values.');

		$this->service->normalizeValue($definition, 'yes');
	}

	public function testNormalizeUrlValueAcceptsValidUrl(): void {
		$definition = $this->buildDefinition(FieldType::URL->value);

		$normalized = $this->service->normalizeValue($definition, 'https://example.com');

		$this->assertSame(['value' => 'https://example.com'], $normalized);
	}

	public function testNormalizeUrlValueTrimsWhitespace(): void {
		$definition = $this->buildDefinition(FieldType::URL->value);

		$normalized = $this->service->normalizeValue($definition, '  https://example.com  ');

		$this->assertSame(['value' => 'https://example.com'], $normalized);
	}

	public function testNormalizeUrlValueRejectsNonUrl(): void {
		$definition = $this->buildDefinition(FieldType::URL->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('URL fields require a valid URL.');

		$this->service->normalizeValue($definition, 'not-a-url');
	}

	public function testNormalizeUrlValueRejectsArray(): void {
		$definition = $this->buildDefinition(FieldType::URL->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('URL fields require a valid URL.');

		$this->service->normalizeValue($definition, ['https://example.com']);
	}

	public function testNormalizeEmailValueAcceptsValidEmail(): void {
		$definition = $this->buildDefinition(FieldType::EMAIL->value);

		$normalized = $this->service->normalizeValue($definition, 'alice@example.com');

		$this->assertSame(['value' => 'alice@example.com'], $normalized);
	}

	public function testNormalizeEmailValueTrimsWhitespace(): void {
		$definition = $this->buildDefinition(FieldType::EMAIL->value);

		$normalized = $this->service->normalizeValue($definition, '  alice@example.com  ');

		$this->assertSame(['value' => 'alice@example.com'], $normalized);
	}

	public function testNormalizeEmailValueRejectsInvalidEmail(): void {
		$definition = $this->buildDefinition(FieldType::EMAIL->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Email fields require a valid email address.');

		$this->service->normalizeValue($definition, 'not-an-email');
	}

	public function testNormalizeEmailValueRejectsArray(): void {
		$definition = $this->buildDefinition(FieldType::EMAIL->value);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Email fields require a valid email address.');

		$this->service->normalizeValue($definition, ['alice@example.com']);
	}

	public function testNormalizeMissingValueAsNull(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);

		$this->assertSame(['value' => null], $this->service->normalizeValue($definition, null));
	}

	public function testUpsertPersistsSerializedValue(): void {
		$definition = $this->buildDefinition(FieldType::NUMBER->value);
		$definition->setId(3);
		$definition->setFieldKey('score');
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);

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
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (object $event): bool {
				$this->assertInstanceOf(ProfileFieldValueCreatedEvent::class, $event);
				$this->assertSame('alice', $event->getWorkflowSubject()->getUserUid());
				$this->assertSame('admin', $event->getWorkflowSubject()->getActorUid());
				$this->assertSame('score', $event->getWorkflowSubject()->getFieldDefinition()->getFieldKey());
				$this->assertSame(9.5, $event->getWorkflowSubject()->getCurrentValue());
				$this->assertNull($event->getWorkflowSubject()->getPreviousValue());
				return true;
			}));

		$stored = $this->service->upsert($definition, 'alice', '9.5', 'admin');

		$this->assertSame('{"value":9.5}', $stored->getValueJson());
	}

	public function testUpsertPreservesImportedUpdatedAt(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);
		$definition->setFieldKey('department');
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);

		$this->fieldValueMapper
			->method('findByFieldDefinitionIdAndUserUid')
			->with(3, 'alice')
			->willReturn(null);

		$this->fieldValueMapper
			->expects($this->once())
			->method('insert')
			->with($this->callback(function (FieldValue $value): bool {
				$this->assertSame('2026-03-12T14:00:00+00:00', $value->getUpdatedAt()->format(DATE_ATOM));
				return true;
			}))
			->willReturnCallback(static fn (FieldValue $value): FieldValue => $value);
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->isInstanceOf(ProfileFieldValueCreatedEvent::class));

		$stored = $this->service->upsert(
			$definition,
			'alice',
			'finance',
			'admin',
			'users',
			new \DateTimeImmutable('2026-03-12T14:00:00+00:00'),
		);

		$this->assertSame('2026-03-12T14:00:00+00:00', $stored->getUpdatedAt()->format(DATE_ATOM));
	}

	public function testUpsertDispatchesUpdatedEventWhenExistingValueChanges(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);
		$definition->setFieldKey('department');
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);

		$existing = new FieldValue();
		$existing->setId(10);
		$existing->setFieldDefinitionId(3);
		$existing->setUserUid('alice');
		$existing->setValueJson('{"value":"finance"}');
		$existing->setCurrentVisibility('users');
		$existing->setUpdatedByUid('alice');
		$existing->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(3, 'alice')
			->willReturn($existing);
		$this->fieldValueMapper->expects($this->once())
			->method('update')
			->willReturnCallback(static fn (FieldValue $value): FieldValue => $value);
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (object $event): bool {
				$this->assertInstanceOf(ProfileFieldValueUpdatedEvent::class, $event);
				$this->assertSame('finance', $event->getWorkflowSubject()->getPreviousValue());
				$this->assertSame('engineering', $event->getWorkflowSubject()->getCurrentValue());
				return true;
			}));

		$this->service->upsert($definition, 'alice', 'engineering', 'admin');
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
		$this->expectExceptionMessage('The stored value payload could not be decoded from JSON.');

		$this->service->serializeForResponse($value);
	}

	public function testUpdateVisibilityUpdatesExistingValue(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);
		$definition->setFieldKey('department');
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
		$this->eventDispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(function (object $event): bool {
				$this->assertInstanceOf(ProfileFieldVisibilityUpdatedEvent::class, $event);
				$this->assertSame('private', $event->getWorkflowSubject()->getPreviousVisibility());
				$this->assertSame('users', $event->getWorkflowSubject()->getCurrentVisibility());
				return true;
			}));

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
		$this->expectExceptionMessage('No profile field value was found.');

		$this->service->updateVisibility($definition, 'alice', 'admin', 'users');
	}

	public function testSearchByDefinitionReturnsPaginatedExactMatches(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);

		$firstMatch = new FieldValue();
		$firstMatch->setId(10);
		$firstMatch->setFieldDefinitionId(3);
		$firstMatch->setUserUid('alice');
		$firstMatch->setValueJson('{"value":"LATAM"}');
		$firstMatch->setCurrentVisibility('users');
		$firstMatch->setUpdatedByUid('admin');
		$firstMatch->setUpdatedAt(new \DateTime());

		$secondMatch = new FieldValue();
		$secondMatch->setId(11);
		$secondMatch->setFieldDefinitionId(3);
		$secondMatch->setUserUid('bob');
		$secondMatch->setValueJson('{"value":"LATAM"}');
		$secondMatch->setCurrentVisibility('users');
		$secondMatch->setUpdatedByUid('admin');
		$secondMatch->setUpdatedAt(new \DateTime());

		$nonMatch = new FieldValue();
		$nonMatch->setId(12);
		$nonMatch->setFieldDefinitionId(3);
		$nonMatch->setUserUid('carol');
		$nonMatch->setValueJson('{"value":"EMEA"}');
		$nonMatch->setCurrentVisibility('users');
		$nonMatch->setUpdatedByUid('admin');
		$nonMatch->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionId')
			->with(3)
			->willReturn([$firstMatch, $secondMatch, $nonMatch]);

		$result = $this->service->searchByDefinition($definition, 'eq', 'LATAM', 1, 1);

		$this->assertSame(2, $result['total']);
		$this->assertCount(1, $result['matches']);
		$this->assertSame('bob', $result['matches'][0]->getUserUid());
	}

	public function testSearchByDefinitionSupportsContainsForTextFields(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);

		$match = new FieldValue();
		$match->setId(10);
		$match->setFieldDefinitionId(3);
		$match->setUserUid('alice');
		$match->setValueJson('{"value":"Ops LATAM"}');
		$match->setCurrentVisibility('users');
		$match->setUpdatedByUid('admin');
		$match->setUpdatedAt(new \DateTime());

		$nonMatch = new FieldValue();
		$nonMatch->setId(11);
		$nonMatch->setFieldDefinitionId(3);
		$nonMatch->setUserUid('bob');
		$nonMatch->setValueJson('{"value":"EMEA"}');
		$nonMatch->setCurrentVisibility('users');
		$nonMatch->setUpdatedByUid('admin');
		$nonMatch->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionId')
			->with(3)
			->willReturn([$match, $nonMatch]);

		$result = $this->service->searchByDefinition($definition, 'contains', 'latam', 50, 0);

		$this->assertSame(1, $result['total']);
		$this->assertCount(1, $result['matches']);
		$this->assertSame('alice', $result['matches'][0]->getUserUid());
	}

	public function testSearchByDefinitionRejectsUnsupportedOperator(): void {
		$definition = $this->buildDefinition(FieldType::TEXT->value);
		$definition->setId(3);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('search operator is not supported');

		$this->service->searchByDefinition($definition, 'starts_with', 'lat', 50, 0);
	}

	public function testNormalizeSelectValueAcceptsValidOption(): void {
		$definition = $this->buildSelectDefinition(['CLT', 'PJ', 'Cooperado']);

		$normalized = $this->service->normalizeValue($definition, 'PJ');

		$this->assertSame(['value' => 'PJ'], $normalized);
	}

	public function testNormalizeSelectValueRejectsValueOutsideOptions(): void {
		$definition = $this->buildSelectDefinition(['CLT', 'PJ', 'Cooperado']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"Freelancer" is not a valid option for this field');

		$this->service->normalizeValue($definition, 'Freelancer');
	}

	public function testNormalizeSelectValueAcceptsNull(): void {
		$definition = $this->buildSelectDefinition(['CLT', 'PJ']);

		$normalized = $this->service->normalizeValue($definition, null);

		$this->assertSame(['value' => null], $normalized);
	}

	public function testNormalizeSelectValueRejectsArray(): void {
		$definition = $this->buildSelectDefinition(['CLT', 'PJ']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Select fields require one of the configured option values.');

		$this->service->normalizeValue($definition, ['CLT']);
	}

	public function testNormalizeSelectValueRejectsInteger(): void {
		$definition = $this->buildSelectDefinition(['1', '2']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Select fields require one of the configured option values.');

		$this->service->normalizeValue($definition, 1);
	}

	public function testNormalizeSelectValueRejectsFloat(): void {
		$definition = $this->buildSelectDefinition(['1.5', '2.5']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Select fields require one of the configured option values.');

		$this->service->normalizeValue($definition, 1.5);
	}

	public function testNormalizeMultiSelectAcceptsArrayOfValidOptions(): void {
		$definition = $this->buildMultiSelectDefinition(['LATAM', 'EMEA', 'APAC']);

		$normalized = $this->service->normalizeValue($definition, ['LATAM', 'APAC']);

		$this->assertSame(['value' => ['LATAM', 'APAC']], $normalized);
	}

	public function testNormalizeMultiSelectRejectsUnknownOption(): void {
		$definition = $this->buildMultiSelectDefinition(['LATAM', 'EMEA', 'APAC']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"ANZ" is not a valid option for this field');

		$this->service->normalizeValue($definition, ['LATAM', 'ANZ']);
	}

	public function testNormalizeMultiSelectRejectsScalar(): void {
		$definition = $this->buildMultiSelectDefinition(['LATAM', 'EMEA']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Multiselect fields require one or more configured option values.');

		$this->service->normalizeValue($definition, 'LATAM');
	}

	private function buildDefinition(string $type): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setType($type);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::PRIVATE->value);
		return $definition;
	}

	/**
	 * @param list<string> $options
	 */
	private function buildSelectDefinition(array $options): FieldDefinition {
		$definition = $this->buildDefinition(FieldType::SELECT->value);
		$definition->setOptions(json_encode($options));
		return $definition;
	}

	/**
	 * @param list<string> $options
	 */
	private function buildMultiSelectDefinition(array $options): FieldDefinition {
		$definition = $this->buildDefinition(FieldType::MULTISELECT->value);
		$definition->setOptions(json_encode($options));
		return $definition;
	}
}
