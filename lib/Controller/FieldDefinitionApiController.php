<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type ProfileFieldsDefinition from \OCA\ProfileFields\ResponseDefinitions
 */
class FieldDefinitionApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private FieldDefinitionService $fieldDefinitionService,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List field definitions
	 *
	 * Return all profile field definitions ordered for admin management.
	 *
	 * @return DataResponse<Http::STATUS_OK, list<ProfileFieldsDefinition>, array{}>
	 *
	 * 200: Field definitions listed successfully
	 */
	#[ApiRoute(verb: 'GET', url: '/api/v1/definitions')]
	public function index(): DataResponse {
		return new DataResponse(array_map(
			static fn (FieldDefinition $definition): array => $definition->jsonSerialize(),
			$this->fieldDefinitionService->findAllOrdered(),
		));
	}

	/**
	 * Create field definition
	 *
	 * Create a new profile field definition for the instance.
	 *
	 * @param string $fieldKey Immutable unique key of the field
	 * @param string $label Human-readable label shown in the UI
	 * @param string $type Value type accepted by the field
	 * @param bool $adminOnly Whether only admins can edit values for this field
	 * @param bool $userEditable Whether the owner can edit the field value
	 * @param bool $userVisible Whether the owner can see the field in personal settings
	 * @param string $initialVisibility Initial visibility applied to new values
	 * @param int $sortOrder Display order used in admin and profile forms
	 * @param bool $active Whether the definition is currently active
	 * @return DataResponse<Http::STATUS_CREATED, ProfileFieldsDefinition, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{message: string}, array{}>
	 *
	 * 201: Field definition created successfully
	 * 400: Invalid field definition payload
	 */
	#[ApiRoute(verb: 'POST', url: '/api/v1/definitions')]
	public function create(
		string $fieldKey,
		string $label,
		string $type,
		bool $adminOnly = false,
		bool $userEditable = false,
		bool $userVisible = true,
		string $initialVisibility = 'private',
		int $sortOrder = 0,
		bool $active = true,
	): DataResponse {
		try {
			$definition = $this->fieldDefinitionService->create([
				'field_key' => $fieldKey,
				'label' => $label,
				'type' => $type,
				'admin_only' => $adminOnly,
				'user_editable' => $userEditable,
				'user_visible' => $userVisible,
				'initial_visibility' => $initialVisibility,
				'sort_order' => $sortOrder,
				'active' => $active,
			]);

			return new DataResponse($definition->jsonSerialize(), Http::STATUS_CREATED);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Update field definition
	 *
	 * Update a profile field definition without changing its immutable key.
	 *
	 * @param int $id Identifier of the field definition
	 * @param string $label Human-readable label shown in the UI
	 * @param string $type Value type accepted by the field
	 * @param bool $adminOnly Whether only admins can edit values for this field
	 * @param bool $userEditable Whether the owner can edit the field value
	 * @param bool $userVisible Whether the owner can see the field in personal settings
	 * @param string $initialVisibility Initial visibility applied to new values
	 * @param int $sortOrder Display order used in admin and profile forms
	 * @param bool $active Whether the definition is currently active
	 * @return DataResponse<Http::STATUS_OK, ProfileFieldsDefinition, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Field definition updated successfully
	 * 400: Invalid field definition payload
	 * 404: Field definition not found
	 */
	#[ApiRoute(verb: 'PUT', url: '/api/v1/definitions/{id}')]
	public function update(
		int $id,
		string $label,
		string $type,
		bool $adminOnly = false,
		bool $userEditable = false,
		bool $userVisible = true,
		string $initialVisibility = 'private',
		int $sortOrder = 0,
		bool $active = true,
	): DataResponse {
		$existing = $this->fieldDefinitionService->findById($id);
		if ($existing === null) {
			return new DataResponse(['message' => 'Field definition not found'], Http::STATUS_NOT_FOUND);
		}

		try {
			$definition = $this->fieldDefinitionService->update($existing, [
				'label' => $label,
				'type' => $type,
				'admin_only' => $adminOnly,
				'user_editable' => $userEditable,
				'user_visible' => $userVisible,
				'initial_visibility' => $initialVisibility,
				'sort_order' => $sortOrder,
				'active' => $active,
			]);

			return new DataResponse($definition->jsonSerialize(), Http::STATUS_OK);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/**
	 * Delete field definition
	 *
	 * Delete a profile field definition and return the removed record.
	 *
	 * @param int $id Identifier of the field definition
	 * @return DataResponse<Http::STATUS_OK, ProfileFieldsDefinition, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: Field definition deleted successfully
	 * 404: Field definition not found
	 */
	#[ApiRoute(verb: 'DELETE', url: '/api/v1/definitions/{id}')]
	public function delete(int $id): DataResponse {
		$definition = $this->fieldDefinitionService->delete($id);
		if ($definition === null) {
			return new DataResponse(['message' => 'Field definition not found'], Http::STATUS_NOT_FOUND);
		}

		return new DataResponse($definition->jsonSerialize(), Http::STATUS_OK);
	}
}
