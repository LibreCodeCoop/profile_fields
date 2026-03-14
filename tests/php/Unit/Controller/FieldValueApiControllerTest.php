<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\Controller\FieldValueApiController;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Service\FieldAccessService;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldValueApiControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueService&MockObject $fieldValueService;
	private FieldAccessService&MockObject $fieldAccessService;
	private FieldValueApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueService = $this->createMock(FieldValueService::class);
		$this->fieldAccessService = $this->createMock(FieldAccessService::class);
		$this->controller = new FieldValueApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->fieldAccessService,
			'alice',
		);
	}

	public function testIndexReturnsOnlyEditableActiveFieldsForAuthenticatedUser(): void {
		$editable = $this->buildDefinition(7, true, false, true);
		$blocked = $this->buildDefinition(8, false, false, true);
		$currentValue = $this->buildValue(7, 'alice');

		$this->fieldDefinitionService->expects($this->once())
			->method('findActiveOrdered')
			->willReturn([$editable, $blocked]);
		$this->fieldAccessService->expects($this->exactly(2))
			->method('canEditValue')
			->willReturnOnConsecutiveCalls(true, false);
		$this->fieldAccessService->expects($this->once())
			->method('canViewValue')
			->with('alice', 'alice', FieldVisibility::USERS->value, false)
			->willReturn(true);
		$this->fieldValueService->expects($this->exactly(2))
			->method('findByFieldDefinitionIdAndUserUid')
			->willReturnMap([
				[7, 'alice', $currentValue],
				[8, 'alice', null],
			]);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($currentValue)
			->willReturn([
				'id' => 4,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'A+'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'alice',
				'updated_at' => $currentValue->getUpdatedAt()->format(DATE_ATOM),
			]);

		$response = $this->controller->index();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertCount(1, $response->getData());
		$this->assertSame(7, $response->getData()[0]['definition']['id']);
		$this->assertSame(['value' => 'A+'], $response->getData()[0]['value']['value']);
	}

	public function testIndexReturnsUnauthorizedWhenUserIsMissing(): void {
		$controller = new FieldValueApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->fieldAccessService,
			null,
		);

		$response = $controller->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['message' => 'Authenticated user is required'], $response->getData());
	}

	#[DataProvider('forbiddenUpsertProvider')]
	public function testUpsertRejectsUnavailableOrForbiddenDefinitions(?FieldDefinition $definition, int $expectedStatus, string $expectedMessage): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);

		if ($definition instanceof FieldDefinition && $definition->getActive()) {
			$this->fieldAccessService->expects($this->once())
				->method('canEditValue')
				->with('alice', 'alice', $definition, false)
				->willReturn(false);
		}

		$response = $this->controller->upsert(7, 'A+', FieldVisibility::PRIVATE->value);

		$this->assertSame($expectedStatus, $response->getStatus());
		$this->assertSame(['message' => $expectedMessage], $response->getData());
	}

	public static function forbiddenUpsertProvider(): array {
		$inactive = new FieldDefinition();
		$inactive->setId(7);
		$inactive->setFieldKey('grade');
		$inactive->setLabel('Grade');
		$inactive->setType(FieldType::TEXT->value);
		$inactive->setAdminOnly(false);
		$inactive->setUserEditable(true);
		$inactive->setUserVisible(true);
		$inactive->setInitialVisibility(FieldVisibility::PRIVATE->value);
		$inactive->setSortOrder(0);
		$inactive->setActive(false);
		$inactive->setCreatedAt(new \DateTime());
		$inactive->setUpdatedAt(new \DateTime());

		$forbidden = clone $inactive;
		$forbidden->setActive(true);

		return [
			'missing definition' => [null, Http::STATUS_NOT_FOUND, 'Field definition not found'],
			'inactive definition' => [$inactive, Http::STATUS_NOT_FOUND, 'Field definition not found'],
			'not editable by user' => [$forbidden, Http::STATUS_FORBIDDEN, 'Field cannot be edited by the user'],
		];
	}

	public function testUpsertReturnsUnauthorizedWhenUserIsMissing(): void {
		$controller = new FieldValueApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->fieldAccessService,
			null,
		);

		$response = $controller->upsert(7, 'A+');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['message' => 'Authenticated user is required'], $response->getData());
	}

	public function testUpsertStoresOwnValueWhenFieldIsEditable(): void {
		$definition = $this->buildDefinition(7, true, false, true);
		$stored = $this->buildValue(7, 'alice');

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldAccessService->expects($this->once())
			->method('canEditValue')
			->with('alice', 'alice', $definition, false)
			->willReturn(true);
		$this->fieldAccessService->expects($this->once())
			->method('canChangeVisibility')
			->with('alice', 'alice', false)
			->willReturn(true);
		$this->fieldValueService->expects($this->once())
			->method('upsert')
			->with($definition, 'alice', 'A+', 'alice', FieldVisibility::USERS->value)
			->willReturn($stored);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($stored)
			->willReturn([
				'id' => 4,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'A+'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'alice',
				'updated_at' => $stored->getUpdatedAt()->format(DATE_ATOM),
			]);

		$response = $this->controller->upsert(7, 'A+', FieldVisibility::USERS->value);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['value' => 'A+'], $response->getData()['value']);
	}

	public function testUpsertReturnsBadRequestOnValidationFailure(): void {
		$definition = $this->buildDefinition(7, true, false, true);

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldAccessService->expects($this->once())
			->method('canEditValue')
			->with('alice', 'alice', $definition, false)
			->willReturn(true);
		$this->fieldValueService->expects($this->once())
			->method('upsert')
			->willThrowException(new InvalidArgumentException('number fields expect a numeric value'));

		$response = $this->controller->upsert(7, null);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'number fields expect a numeric value'], $response->getData());
	}

	#[DataProvider('updateVisibilityRejectionProvider')]
	public function testUpdateVisibilityRejectsUnavailableOrForbiddenDefinitions(?FieldDefinition $definition, ?bool $canEditValue, ?bool $canChangeVisibility, int $expectedStatus, string $expectedMessage): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);

		if ($definition instanceof FieldDefinition && $definition->getActive()) {
			$this->fieldAccessService->expects($this->once())
				->method('canEditValue')
				->with('alice', 'alice', $definition, false)
				->willReturn($canEditValue ?? false);

			if ($canEditValue) {
				$this->fieldAccessService->expects($this->once())
					->method('canChangeVisibility')
					->with('alice', 'alice', false)
					->willReturn($canChangeVisibility ?? false);
			}
		}

		$response = $this->controller->updateVisibility(7, FieldVisibility::USERS->value);

		$this->assertSame($expectedStatus, $response->getStatus());
		$this->assertSame(['message' => $expectedMessage], $response->getData());
	}

	public static function updateVisibilityRejectionProvider(): array {
		$inactive = new FieldDefinition();
		$inactive->setId(7);
		$inactive->setFieldKey('grade');
		$inactive->setLabel('Grade');
		$inactive->setType(FieldType::TEXT->value);
		$inactive->setAdminOnly(false);
		$inactive->setUserEditable(true);
		$inactive->setUserVisible(true);
		$inactive->setInitialVisibility(FieldVisibility::PRIVATE->value);
		$inactive->setSortOrder(0);
		$inactive->setActive(false);
		$inactive->setCreatedAt(new \DateTime());
		$inactive->setUpdatedAt(new \DateTime());

		$forbidden = clone $inactive;
		$forbidden->setActive(true);
		$editableButVisibilityForbidden = clone $inactive;
		$editableButVisibilityForbidden->setActive(true);
		$editableButVisibilityForbidden->setUserEditable(true);
		$editableButVisibilityForbidden->setUserVisible(true);

		return [
			'missing definition' => [null, null, null, Http::STATUS_NOT_FOUND, 'Field definition not found'],
			'inactive definition' => [$inactive, null, null, Http::STATUS_NOT_FOUND, 'Field definition not found'],
			'not editable by user' => [$forbidden, false, null, Http::STATUS_FORBIDDEN, 'Field cannot be edited by the user'],
			'visibility forbidden' => [$editableButVisibilityForbidden, true, false, Http::STATUS_FORBIDDEN, 'Field visibility cannot be changed by the user'],
		];
	}

	public function testUpdateVisibilityReturnsUnauthorizedWhenUserIsMissing(): void {
		$controller = new FieldValueApiController(
			$this->request,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->fieldAccessService,
			null,
		);

		$response = $controller->updateVisibility(7, FieldVisibility::USERS->value);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['message' => 'Authenticated user is required'], $response->getData());
	}

	public function testUpdateVisibilityStoresUpdatedVisibility(): void {
		$definition = $this->buildDefinition(7, true, false, true);
		$stored = $this->buildValue(7, 'alice');

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldAccessService->expects($this->once())
			->method('canEditValue')
			->with('alice', 'alice', $definition, false)
			->willReturn(true);
		$this->fieldAccessService->expects($this->once())
			->method('canChangeVisibility')
			->with('alice', 'alice', false)
			->willReturn(true);
		$this->fieldValueService->expects($this->once())
			->method('updateVisibility')
			->with($definition, 'alice', 'alice', FieldVisibility::PUBLIC->value)
			->willReturn($stored);
		$this->fieldValueService->expects($this->once())
			->method('serializeForResponse')
			->with($stored)
			->willReturn([
				'id' => 4,
				'field_definition_id' => 7,
				'user_uid' => 'alice',
				'value' => ['value' => 'A+'],
				'current_visibility' => 'public',
				'updated_by_uid' => 'alice',
				'updated_at' => $stored->getUpdatedAt()->format(DATE_ATOM),
			]);

		$response = $this->controller->updateVisibility(7, FieldVisibility::PUBLIC->value);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('public', $response->getData()['current_visibility']);
	}

	public function testUpdateVisibilityReturnsBadRequestOnServiceValidationFailure(): void {
		$definition = $this->buildDefinition(7, true, false, true);

		$this->fieldDefinitionService->expects($this->once())
			->method('findById')
			->with(7)
			->willReturn($definition);
		$this->fieldAccessService->expects($this->once())
			->method('canEditValue')
			->with('alice', 'alice', $definition, false)
			->willReturn(true);
		$this->fieldAccessService->expects($this->once())
			->method('canChangeVisibility')
			->with('alice', 'alice', false)
			->willReturn(true);
		$this->fieldValueService->expects($this->once())
			->method('updateVisibility')
			->willThrowException(new InvalidArgumentException('field value not found'));

		$response = $this->controller->updateVisibility(7, FieldVisibility::PUBLIC->value);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'field value not found'], $response->getData());
	}

	private function buildDefinition(int $id, bool $userEditable, bool $adminOnly, bool $active): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId($id);
		$definition->setFieldKey('grade_' . $id);
		$definition->setLabel('Grade ' . $id);
		$definition->setType(FieldType::TEXT->value);
		$definition->setAdminOnly($adminOnly);
		$definition->setUserEditable($userEditable);
		$definition->setUserVisible(true);
		$definition->setInitialVisibility(FieldVisibility::PRIVATE->value);
		$definition->setSortOrder(0);
		$definition->setActive($active);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());
		return $definition;
	}

	private function buildValue(int $fieldDefinitionId, string $userUid): FieldValue {
		$value = new FieldValue();
		$value->setId(4);
		$value->setFieldDefinitionId($fieldDefinitionId);
		$value->setUserUid($userUid);
		$value->setValueJson('{"value":"A+"}');
		$value->setCurrentVisibility(FieldVisibility::USERS->value);
		$value->setUpdatedByUid($userUid);
		$value->setUpdatedAt(new \DateTime());
		return $value;
	}
}
