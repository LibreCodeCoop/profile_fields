<?php

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
class FieldDefinitionApiControllerTest extends ApiTestCase {
	private const ADMIN_USER_ID = 'pf_api_admin';
	private const ADMIN_PASSWORD = 'pf_api_admin';

	protected function setUp(): void {
		parent::setUp();

		$this->ensureAdminUser(self::ADMIN_USER_ID, self::ADMIN_PASSWORD);
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
	public function testAdminDefinitionListMatchesOpenApiContract(): void {
		$fieldKey = $this->uniqueFieldKey('api_definition_contract_field');
		$this->createDefinition(
			$fieldKey,
			'API contract field',
			FieldType::TEXT->value,
			false,
			true,
			FieldVisibility::USERS->value,
			10,
			true,
		);

		$response = $this->withBasicAuth($this->newApiRequester(), self::ADMIN_USER_ID, self::ADMIN_PASSWORD)
			->withMethod('GET')
			->withPath('/ocs/v2.php/apps/profile_fields/api/v1/definitions')
			->assertResponseCode(200)
			->assertBodyContains($fieldKey)
			->send();

		$this->assertSame(200, $response->getStatusCode());
	}
}
