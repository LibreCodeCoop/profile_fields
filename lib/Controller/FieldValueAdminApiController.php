<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @psalm-import-type ProfileFieldsValuePayload from \OCA\ProfileFields\ResponseDefinitions
 * @psalm-import-type ProfileFieldsLookupField from \OCA\ProfileFields\ResponseDefinitions
 * @psalm-import-type ProfileFieldsSearchResult from \OCA\ProfileFields\ResponseDefinitions
 * @psalm-import-type ProfileFieldsValueRecord from \OCA\ProfileFields\ResponseDefinitions
 */
class FieldValueAdminApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueService $fieldValueService,
		private IUserManager $userManager,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List stored values for a user
	 *
	 * Return all persisted profile field values for a specific user.
	 *
	 * @param string $userUid User identifier whose profile field values should be listed
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, list<ProfileFieldsValueRecord>, array{}>
	 *
	 * 200: User field values listed successfully
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/users/{userUid}/values')]
	public function index(string $userUid): DataResponse {
		return new DataResponse(array_map(
			fn (FieldValue $value): array => $this->fieldValueService->serializeForResponse($value),
			$this->fieldValueService->findByUserUid($userUid),
		));
	}

	/**
	 * Upsert a stored value for a user
	 *
	 * Create or update a profile field value for a specific user as an administrator.
	 *
	 * @param string $userUid User identifier that owns the profile field value
	 * @param int $fieldDefinitionId Identifier of the field definition
	 * @param array{value?: string|int|float|bool|null}|string|int|float|bool|null $value Value payload to persist
	 * @param string|null $currentVisibility Visibility to apply to the stored value
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, ProfileFieldsValueRecord, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_BAD_REQUEST|\OCP\AppFramework\Http::STATUS_NOT_FOUND|\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: User field value stored successfully
	 * 400: Invalid field value payload
	 * 401: Authenticated admin user is required
	 * 404: Field definition not found
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/v1/users/{userUid}/values/{fieldDefinitionId}')]
	public function upsert(
		string $userUid,
		int $fieldDefinitionId,
		array|string|int|float|bool|null $value = null,
		?string $currentVisibility = null,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => 'Authenticated admin user is required'], Http::STATUS_UNAUTHORIZED);
		}

		$definition = $this->fieldDefinitionService->findById($fieldDefinitionId);
		if ($definition === null || !$definition->getActive()) {
			return new DataResponse(['message' => 'Field definition not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$stored = $this->fieldValueService->upsert($definition, $userUid, $value, $this->userId, $currentVisibility);

			return new DataResponse($this->fieldValueService->serializeForResponse($stored), Http::STATUS_OK);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Lookup a user by a key profile field value
	 *
	 * Resolve a Nextcloud user from an exact profile field match, then return the user's stored profile
	 * fields keyed by field key. This is intended for ETL or payroll-style integrations that know one
	 * authoritative identifier such as CPF and need the rest of the cooperative data.
	 *
	 * @param string $fieldKey Immutable key of the lookup field, such as cpf
	 * @param array{value?: string|int|float|bool|null}|string|int|float|bool|null $fieldValue Value payload to match exactly
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, array{user_uid: string, lookup_field_key: string, fields: array<string, array{definition: array<string, mixed>, value: ProfileFieldsValueRecord}>}, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_BAD_REQUEST|\OCP\AppFramework\Http::STATUS_CONFLICT|\OCP\AppFramework\Http::STATUS_NOT_FOUND|\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: User lookup completed successfully
	 * 400: Invalid lookup payload
	 * 401: Authenticated admin user is required
	 * 404: Lookup field definition or user not found
	 * 409: Multiple users match the lookup field value
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/users/lookup')]
	public function lookup(
		string $fieldKey,
		array|string|int|float|bool|null $fieldValue = null,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => 'Authenticated admin user is required'], Http::STATUS_UNAUTHORIZED);
		}

		$definition = $this->fieldDefinitionService->findByFieldKey($fieldKey);
		if ($definition === null || !$definition->getActive()) {
			return new DataResponse(['message' => 'Lookup field definition not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$matches = $this->fieldValueService->findByDefinitionAndRawValue($definition, $fieldValue);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		if ($matches === []) {
			return new DataResponse(['message' => 'User not found for lookup field value'], Http::STATUS_NOT_FOUND);
		}

		if (count($matches) > 1) {
			return new DataResponse(['message' => 'Multiple users match the lookup field value'], Http::STATUS_CONFLICT);
		}

		return new DataResponse($this->serializeLookupResult($definition, $matches[0]), Http::STATUS_OK);
	}

	/**
	 * Search users by one profile field filter
	 *
	 * Return a paginated list of users that match one explicit profile field filter. The response
	 * includes only the field/value pair that produced the match, not the full profile.
	 *
	 * @param string $fieldKey Immutable key of the field to filter by
	 * @param string $operator Explicit search operator, currently `eq` or `contains`
	 * @param string|null $value Value payload to compare against the stored field value
	 * @param int $limit Maximum number of users to return in the current page
	 * @param int $offset Zero-based offset into the matched result set
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, ProfileFieldsSearchResult, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_BAD_REQUEST|\OCP\AppFramework\Http::STATUS_NOT_FOUND|\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: User search completed successfully
	 * 400: Invalid search filter or pagination values
	 * 401: Authenticated admin user is required
	 * 404: Search field definition not found
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/users/search')]
	public function search(
		string $fieldKey,
		string $operator = 'eq',
		?string $value = null,
		int $limit = 50,
		int $offset = 0,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => 'Authenticated admin user is required'], Http::STATUS_UNAUTHORIZED);
		}

		$definition = $this->fieldDefinitionService->findByFieldKey($fieldKey);
		if ($definition === null || !$definition->getActive()) {
			return new DataResponse(['message' => 'Search field definition not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$search = $this->fieldValueService->searchByDefinition($definition, $operator, $value, $limit, $offset);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$items = array_map(
			fn (FieldValue $matchedValue): array => $this->serializeSearchItem($definition, $matchedValue),
			$search['matches'],
		);

		return new DataResponse([
			'items' => $items,
			'pagination' => [
				'limit' => $limit,
				'offset' => $offset,
				'total' => $search['total'],
			],
		], Http::STATUS_OK);
	}

	/**
	 * @return array{user_uid: string, lookup_field_key: string, fields: array<string, array{definition: array<string, mixed>, value: ProfileFieldsValueRecord}>}
	 */
	private function serializeLookupResult(FieldDefinition $lookupDefinition, FieldValue $matchedValue): array {
		$definitionsById = [];
		foreach ($this->fieldDefinitionService->findAllOrdered() as $definition) {
			if (!$definition->getActive()) {
				continue;
			}

			$definitionsById[$definition->getId()] = $definition;
		}

		$fields = [];
		foreach ($this->fieldValueService->findByUserUid($matchedValue->getUserUid()) as $storedValue) {
			$definition = $definitionsById[$storedValue->getFieldDefinitionId()] ?? null;
			if ($definition === null) {
				continue;
			}

			$fields[$definition->getFieldKey()] = [
				'definition' => $definition->jsonSerialize(),
				'value' => $this->fieldValueService->serializeForResponse($storedValue),
			];
		}

		return [
			'user_uid' => $matchedValue->getUserUid(),
			'lookup_field_key' => $lookupDefinition->getFieldKey(),
			'fields' => $fields,
		];
	}

	/**
	 * @return array{user_uid: string, display_name: string, fields: array<string, ProfileFieldsLookupField>}
	 */
	private function serializeSearchItem(FieldDefinition $definition, FieldValue $matchedValue): array {
		$user = $this->userManager->get($matchedValue->getUserUid());

		return [
			'user_uid' => $matchedValue->getUserUid(),
			'display_name' => $this->resolveDisplayName($user, $matchedValue->getUserUid()),
			'fields' => [
				$definition->getFieldKey() => [
					'definition' => $definition->jsonSerialize(),
					'value' => $this->fieldValueService->serializeForResponse($matchedValue),
				],
			],
		];
	}

	private function resolveDisplayName(?IUser $user, string $fallbackUserUid): string {
		if ($user === null) {
			return $fallbackUserUid;
		}

		$displayName = $user->getDisplayName();
		return $displayName !== '' ? $displayName : $fallbackUserUid;
	}
}
