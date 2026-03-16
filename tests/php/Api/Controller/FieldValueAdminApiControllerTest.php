<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
class FieldValueAdminApiControllerTest extends ApiTestCase {
	private const ADMIN_USER_ID = 'pf_api_admin_values';
	private const ADMIN_PASSWORD = 'pf_api_admin_values';
	private const OWNER_USER_ID = 'pf_api_owner_values';

	protected function setUp(): void {
		parent::setUp();

		$this->ensureAdminUser(self::ADMIN_USER_ID, self::ADMIN_PASSWORD);
		$this->createUser(self::OWNER_USER_ID, self::OWNER_USER_ID);
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
	public function testAdminValueListMatchesOpenApiContract(): void {
		$definition = $this->createDefinition(
			$this->uniqueFieldKey('api_admin_value_contract_field'),
			'Admin API contract field',
			FieldType::NUMBER->value,
			false,
			true,
			FieldVisibility::PUBLIC->value,
			20,
			true,
		);
		$this->createStoredValue($definition, self::OWNER_USER_ID, 95, self::ADMIN_USER_ID, FieldVisibility::PUBLIC->value);

		$response = $this->withBasicAuth($this->newApiRequester(), self::ADMIN_USER_ID, self::ADMIN_PASSWORD)
			->withMethod('GET')
			->withPath('/ocs/v2.php/apps/profile_fields/api/v1/users/' . self::OWNER_USER_ID . '/values')
			->assertResponseCode(200)
			->assertBodyContains(self::OWNER_USER_ID)
			->send();

		$this->assertSame(200, $response->getStatusCode());
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
	public function testAdminSearchMatchesOpenApiContract(): void {
		$fieldKey = $this->uniqueFieldKey('api_admin_search_contract_field');
		$definition = $this->createDefinition(
			$fieldKey,
			'Admin API search contract field',
			FieldType::TEXT->value,
			false,
			true,
			FieldVisibility::PUBLIC->value,
			20,
			true,
		);
		$this->createStoredValue($definition, self::OWNER_USER_ID, 'LATAM', self::ADMIN_USER_ID, FieldVisibility::PUBLIC->value);

		$response = $this->withBasicAuth($this->newApiRequester(), self::ADMIN_USER_ID, self::ADMIN_PASSWORD)
			->withMethod('GET')
			->withPath('/ocs/v2.php/apps/profile_fields/api/v1/users/search?fieldKey=' . rawurlencode($fieldKey) . '&operator=contains&value=lat')
			->assertResponseCode(200)
			->assertBodyContains(self::OWNER_USER_ID)
			->send();

		$this->assertSame(200, $response->getStatusCode());
	}
}
