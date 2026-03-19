<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\Controller\FieldValueAdminApiController;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldValueAdminApiControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueService&MockObject $fieldValueService;
	private IUserManager&MockObject $userManager;
	private FieldValueAdminApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueService = $this->createMock(FieldValueService::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->controller = new FieldValueAdminApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->userManager,
			'admin',
		);
	}

	public function testIndexReturnsSerializedValuesForUser(): void {
		$value = $this->buildValue();
		$this->fieldValueService->expects($this->once())
			->method('findByUserUid')
			->with('alice')
			->willReturn([$value]);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($value)
			->willReturn([
				'id' => 3,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'A+'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'admin',
				'updated_at' => $value->getUpdatedAt()->format(DATE_ATOM),
			]);

		$response = $this->controller->index('alice');

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData());
		$this->assertSame(['value' => 'A+'], $response->getData()[0]['value']);
	}

	public function testUpsertReturnsNotFoundWhenDefinitionDoesNotExist(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn(null);

		$response = $this->controller->upsert('alice', 7, 'A+', FieldVisibility::USERS->value);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Field definition not found'], $response->getData());
	}

	public function testUpsertReturnsUnauthorizedWithoutAuthenticatedAdminUser(): void {
		$controller = new FieldValueAdminApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->userManager,
			null,
		);

		$response = $controller->upsert('alice', 7, 'A+', FieldVisibility::USERS->value);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['message' => 'Authenticated admin user is required'], $response->getData());
	}

	public function testUpsertReturnsSerializedValue(): void {
		$definition = $this->buildDefinition();
		$value = $this->buildValue();

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldValueService->expects($this->once())
			->method('upsert')
			->with($definition, 'alice', 'A+', 'admin', FieldVisibility::PRIVATE->value)
			->willReturn($value);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($value)
			->willReturn([
				'id' => 3,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'A+'],
				'current_visibility' => 'private',
				'updated_by_uid' => 'admin',
				'updated_at' => $value->getUpdatedAt()->format(DATE_ATOM),
			]);

		$response = $this->controller->upsert('alice', 7, 'A+', FieldVisibility::PRIVATE->value);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['value' => 'A+'], $response->getData()['value']);
	}

	public function testUpsertReturnsBadRequestOnValidationFailure(): void {
		$definition = $this->buildDefinition();

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldValueService->expects($this->once())
			->method('upsert')
			->willThrowException(new InvalidArgumentException('number fields expect a numeric value'));

		$response = $this->controller->upsert('alice', 7, null, FieldVisibility::PRIVATE->value);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'number fields expect a numeric value'], $response->getData());
	}

	public function testLookupReturnsFieldSnapshotsForMatchedUser(): void {
		$definition = $this->buildDefinition();
		$matchedValue = $this->buildValue();
		$otherDefinition = new FieldDefinition();
		$otherDefinition->setId(8);
		$otherDefinition->setFieldKey('health_plan_type');
		$otherDefinition->setLabel('Health plan type');
		$otherDefinition->setType(FieldType::TEXT->value);
		$otherDefinition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::ADMINS->value);
		$otherDefinition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::PRIVATE->value);
		$otherDefinition->setSortOrder(1);
		$otherDefinition->setActive(true);
		$otherDefinition->setCreatedAt(new \DateTime());
		$otherDefinition->setUpdatedAt(new \DateTime());

		$otherValue = new FieldValue();
		$otherValue->setId(4);
		$otherValue->setFieldDefinitionId(8);
		$otherValue->setUserUid('alice');
		$otherValue->setValueJson('{"value":"coop-premium"}');
		$otherValue->setCurrentVisibility(FieldVisibility::PRIVATE->value);
		$otherValue->setUpdatedByUid('admin');
		$otherValue->setUpdatedAt(new \DateTime());

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('cpf')
			->willReturn($definition);
		$this->fieldValueService->expects($this->once())
			->method('findByDefinitionAndRawValue')
			->with($definition, '12345678900')
			->willReturn([$matchedValue]);
		$this->fieldDefinitionService->expects($this->once())
			->method('findAllOrdered')
			->willReturn([$definition, $otherDefinition]);
		$this->fieldValueService->expects($this->once())
			->method('findByUserUid')
			->with('alice')
			->willReturn([$matchedValue, $otherValue]);
		$this->fieldValueService->expects($this->exactly(2))
			->method('serializeForResponse')
			->willReturnCallback(static function (FieldValue $value): array {
				return [
					'id' => $value->getId(),
					'field_definition_id' => $value->getFieldDefinitionId(),
					'user_uid' => $value->getUserUid(),
					'value' => json_decode($value->getValueJson(), true, flags: JSON_THROW_ON_ERROR),
					'current_visibility' => $value->getCurrentVisibility(),
					'updated_by_uid' => $value->getUpdatedByUid(),
					'updated_at' => $value->getUpdatedAt()->format(DATE_ATOM),
				];
			});

		$response = $this->controller->lookup('cpf', '12345678900');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('alice', $response->getData()['user_uid']);
		$this->assertSame('cpf', $response->getData()['lookup_field_key']);
		$this->assertSame(['value' => '12345678900'], $response->getData()['fields']['cpf']['value']['value']);
		$this->assertSame(['value' => 'coop-premium'], $response->getData()['fields']['health_plan_type']['value']['value']);
	}

	public function testLookupReturnsConflictWhenMoreThanOneUserMatches(): void {
		$definition = $this->buildDefinition();
		$firstMatch = $this->buildValue();
		$secondMatch = $this->buildValue();
		$secondMatch->setId(5);
		$secondMatch->setUserUid('bob');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('cpf')
			->willReturn($definition);
		$this->fieldValueService->expects($this->once())
			->method('findByDefinitionAndRawValue')
			->willReturn([$firstMatch, $secondMatch]);

		$response = $this->controller->lookup('cpf', '12345678900');

		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame(['message' => 'Multiple users match the lookup field value'], $response->getData());
	}

	public function testSearchReturnsPaginatedMatchesWithDisplayNames(): void {
		$definition = $this->buildDefinition();
		$definition->setFieldKey('region');
		$firstMatch = $this->buildValue();
		$secondMatch = $this->buildValue();
		$secondMatch->setId(4);
		$secondMatch->setUserUid('bob');
		$secondMatch->setValueJson('{"value":"LATAM 2"}');

		$alice = $this->createMock(IUser::class);
		$alice->method('getDisplayName')->willReturn('Alice Doe');
		$bob = $this->createMock(IUser::class);
		$bob->method('getDisplayName')->willReturn('Bob Doe');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('region')
			->willReturn($definition);
		$this->fieldValueService->expects($this->once())
			->method('searchByDefinition')
			->with($definition, 'contains', 'latam', 1, 1)
			->willReturn([
				'total' => 2,
				'matches' => [$secondMatch],
			]);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($secondMatch)
			->willReturn([
				'id' => 4,
				'field_definition_id' => 7,
				'user_uid' => 'bob',
				'value' => ['value' => 'LATAM 2'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'admin',
				'updated_at' => $secondMatch->getUpdatedAt()->format(DATE_ATOM),
			]);
		$this->userManager->expects($this->once())
			->method('get')
			->with('bob')
			->willReturn($bob);

		$response = $this->controller->search('region', 'contains', 'latam', 1, 1);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(2, $response->getData()['pagination']['total']);
		$this->assertSame(1, $response->getData()['pagination']['limit']);
		$this->assertSame(1, $response->getData()['pagination']['offset']);
		$this->assertCount(1, $response->getData()['items']);
		$this->assertSame('bob', $response->getData()['items'][0]['user_uid']);
		$this->assertSame('Bob Doe', $response->getData()['items'][0]['display_name']);
		$this->assertSame(['value' => 'LATAM 2'], $response->getData()['items'][0]['fields']['region']['value']['value']);
	}

	public function testSearchReturnsNotFoundWhenDefinitionDoesNotExist(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('region')
			->willReturn(null);

		$response = $this->controller->search('region', 'eq', 'LATAM');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Search field definition not found'], $response->getData());
	}

	private function buildDefinition(): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId(7);
		$definition->setFieldKey('cpf');
		$definition->setLabel('CPF');
		$definition->setType(FieldType::TEXT->value);
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::ADMINS->value);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::PRIVATE->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());
		return $definition;
	}

	private function buildValue(): FieldValue {
		$value = new FieldValue();
		$value->setId(3);
		$value->setFieldDefinitionId(7);
		$value->setUserUid('alice');
		$value->setValueJson('{"value":"12345678900"}');
		$value->setCurrentVisibility(FieldVisibility::USERS->value);
		$value->setUpdatedByUid('admin');
		$value->setUpdatedAt(new \DateTime());
		return $value;
	}
}
