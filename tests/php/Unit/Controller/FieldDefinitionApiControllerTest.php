<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\Controller\FieldDefinitionApiController;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FieldDefinitionApiControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private FieldDefinitionService&MockObject $service;
	private FieldDefinitionApiController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->request = $this->createMock(IRequest::class);
		$this->service = $this->createMock(FieldDefinitionService::class);
		$this->controller = new FieldDefinitionApiController($this->request, $this->service);
	}

	public function testListReturnsSerializedDefinitions(): void {
		$definition = $this->buildDefinition(3, 'performance_score');
		$this->service->expects($this->once())
			->method('findAllOrdered')
			->willReturn([$definition]);

		$response = $this->controller->index();

		$this->assertInstanceOf(DataResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$definition->jsonSerialize()], $response->getData());
	}

	public function testCreateReturnsCreatedDefinition(): void {
		$definition = $this->buildDefinition(5, 'cpf');
		$this->service->expects($this->once())
			->method('create')
			->with([
				'field_key' => 'cpf',
				'label' => 'CPF',
				'type' => FieldType::TEXT->value,
				'edit_policy' => FieldEditPolicy::USERS->value,
				'exposure_policy' => FieldExposurePolicy::USERS->value,
				'sort_order' => 2,
				'active' => true,
			])
			->willReturn($definition);

		$response = $this->controller->create(
			'cpf',
			'CPF',
			FieldType::TEXT->value,
			FieldEditPolicy::USERS->value,
			FieldExposurePolicy::USERS->value,
			2,
			true,
		);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame($definition->jsonSerialize(), $response->getData());
	}

	public function testCreateSelectFieldForwardsOptions(): void {
		$definition = $this->buildDefinition(6, 'contract_type');
		$this->service->expects($this->once())
			->method('create')
			->with([
				'field_key' => 'contract_type',
				'label' => 'Contract Type',
				'type' => FieldType::SELECT->value,
				'edit_policy' => FieldEditPolicy::USERS->value,
				'exposure_policy' => FieldExposurePolicy::PRIVATE->value,
				'sort_order' => 0,
				'active' => true,
				'options' => ['CLT', 'PJ', 'Cooperado'],
			])
			->willReturn($definition);

		$response = $this->controller->create(
			'contract_type',
			'Contract Type',
			FieldType::SELECT->value,
			FieldEditPolicy::USERS->value,
			FieldExposurePolicy::PRIVATE->value,
			0,
			true,
			['CLT', 'PJ', 'Cooperado'],
		);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
	}

	public function testCreateReturnsBadRequestOnValidationFailure(): void {
		$this->service->expects($this->once())
			->method('create')
			->willThrowException(new InvalidArgumentException('field_key already exists'));

		$response = $this->controller->create(
			'cpf',
			'CPF',
			FieldType::TEXT->value,
			FieldEditPolicy::ADMINS->value,
			FieldExposurePolicy::PRIVATE->value,
			0,
			true,
		);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['message' => 'field_key already exists'], $response->getData());
	}

	public function testUpdateSelectFieldForwardsOptions(): void {
		$existing = $this->buildDefinition(6, 'contract_type');
		$updated = $this->buildDefinition(6, 'contract_type');

		$this->service->expects($this->once())
			->method('findById')
			->with(6)
			->willReturn($existing);

		$this->service->expects($this->once())
			->method('update')
			->with($existing, [
				'label' => 'Contract Type',
				'type' => FieldType::SELECT->value,
				'edit_policy' => FieldEditPolicy::USERS->value,
				'exposure_policy' => FieldExposurePolicy::PRIVATE->value,
				'sort_order' => 0,
				'active' => true,
				'options' => ['CLT', 'PJ'],
			])
			->willReturn($updated);

		$response = $this->controller->update(
			6,
			'Contract Type',
			FieldType::SELECT->value,
			FieldEditPolicy::USERS->value,
			FieldExposurePolicy::PRIVATE->value,
			0,
			true,
			['CLT', 'PJ'],
		);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testUpdateReturnsNotFoundWhenDefinitionDoesNotExist(): void {
		$this->service->expects($this->once())
			->method('findById')
			->with(99)
			->willReturn(null);

		$response = $this->controller->update(
			99,
			'CPF',
			FieldType::TEXT->value,
			FieldEditPolicy::ADMINS->value,
			FieldExposurePolicy::PRIVATE->value,
			0,
			true,
		);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['message' => 'Field definition not found'], $response->getData());
	}

	public function testDeleteReturnsDeletedDefinition(): void {
		$definition = $this->buildDefinition(8, 'rg');
		$this->service->expects($this->once())
			->method('delete')
			->with(8)
			->willReturn($definition);

		$response = $this->controller->delete(8);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($definition->jsonSerialize(), $response->getData());
	}

	private function buildDefinition(int $id, string $fieldKey): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId($id);
		$definition->setFieldKey($fieldKey);
		$definition->setLabel(strtoupper($fieldKey));
		$definition->setType(FieldType::TEXT->value);
		$definition->setEditPolicy(FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(FieldExposurePolicy::PRIVATE->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());
		return $definition;
	}
}
