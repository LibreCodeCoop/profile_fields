<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Api\Controller;

use ByJG\ApiTools\Exception\DefinitionNotFoundException;
use ByJG\ApiTools\Exception\GenericSwaggerException;
use ByJG\ApiTools\Exception\HttpMethodNotFoundException;
use ByJG\ApiTools\Exception\InvalidDefinitionException;
use ByJG\ApiTools\Exception\InvalidRequestException;
use ByJG\ApiTools\Exception\NotMatchedException;
use ByJG\ApiTools\Exception\PathNotFoundException;
use ByJG\ApiTools\Exception\RequiredArgumentNotFound;
use ByJG\ApiTools\Exception\StatusCodeNotMatchedException;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Tests\Api\ApiTestCase;

/**
 * @group DB
 */
class FieldValueApiControllerTest extends ApiTestCase {
	private const USER_ID = 'pf_api_self_values';
	private const USER_PASSWORD = 'pf_api_self_values';

	protected function setUp(): void {
		parent::setUp();

		$this->createUser(self::USER_ID, self::USER_PASSWORD);
	}

	/**
	 * @throws DefinitionNotFoundException
	 * @throws GenericSwaggerException
	 * @throws HttpMethodNotFoundException
	 * @throws InvalidDefinitionException
	 * @throws InvalidRequestException
	 * @throws NotMatchedException
	 * @throws PathNotFoundException
	 * @throws RequiredArgumentNotFound
	 * @throws StatusCodeNotMatchedException
	 */
	public function testEditableFieldVisibilityUpdateMatchesOpenApiContract(): void {
		$definition = $this->createDefinition(
			$this->uniqueFieldKey('api_self_value_contract_field'),
			'Self API contract field',
			FieldType::NUMBER->value,
			false,
			true,
			FieldVisibility::USERS->value,
			30,
			true,
		);
		$this->createStoredValue($definition, self::USER_ID, 42, self::USER_ID, FieldVisibility::PRIVATE->value);

		$response = $this->withBasicAuth($this->newApiRequester(), self::USER_ID, self::USER_PASSWORD)
			->withMethod('PUT')
			->withPath('/ocs/v2.php/apps/profile_fields/api/v1/me/values/' . $definition->getId() . '/visibility')
			->withRequestHeader(['Content-Type' => 'application/json'])
			->withRequestBody([
				'currentVisibility' => FieldVisibility::USERS->value,
			])
			->assertResponseCode(200)
			->assertBodyContains(self::USER_ID)
			->send();

		$this->assertSame(200, $response->getStatusCode());
	}
}
