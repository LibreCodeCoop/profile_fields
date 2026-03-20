<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Controller;

use InvalidArgumentException;
use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Service\FieldAccessService;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;

/**
 * @psalm-import-type ProfileFieldsDefinition from \OCA\ProfileFields\ResponseDefinitions
 * @psalm-import-type ProfileFieldsEditableField from \OCA\ProfileFields\ResponseDefinitions
 * @psalm-import-type ProfileFieldsValueRecord from \OCA\ProfileFields\ResponseDefinitions
 */
class FieldValueApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueService $fieldValueService,
		private FieldAccessService $fieldAccessService,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List user-visible fields for the authenticated user
	 *
	 * Return active profile fields visible to the authenticated user, together with any current stored value
	 * and whether the user can edit the value directly.
	 *
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, list<ProfileFieldsEditableField>, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: Editable profile fields listed successfully
	 * 401: Authenticated user is required
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/api/v1/me/values')]
	public function index(): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => $this->l10n->t('Authenticated user is required')], 401);
		}

		$definitions = $this->fieldDefinitionService->findActiveOrdered();
		$editableFields = [];

		foreach ($definitions as $definition) {
			if (!FieldExposurePolicy::from($definition->getExposurePolicy())->isUserVisible()) {
				continue;
			}

			$currentValue = $this->fieldValueService->findByFieldDefinitionIdAndUserUid($definition->getId(), $this->userId);
			$canEdit = $this->fieldAccessService->canEditValue($this->userId, $this->userId, $definition, false);

			if ($currentValue !== null && !$this->fieldAccessService->canViewValue(
				$this->userId,
				$this->userId,
				$currentValue->getCurrentVisibility(),
				false,
			)) {
				continue;
			}

			if (!$canEdit && $currentValue === null) {
				continue;
			}

			$editableFields[] = [
				'definition' => $definition->jsonSerialize(),
				'value' => $currentValue === null ? null : $this->fieldValueService->serializeForResponse($currentValue),
				'can_edit' => $canEdit,
			];
		}

		return new DataResponse($editableFields, 200);
	}

	/**
	 * Upsert the authenticated user's value for an editable field
	 *
	 * Create or update the current user's value for a field that allows self-service editing.
	 *
	 * @param int $fieldDefinitionId Identifier of the field definition
	 * @param array{value?: string|int|float|bool|null}|string|int|float|bool|null $value Value payload to persist
	 * @param string|null $currentVisibility Visibility to apply to the stored value
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, ProfileFieldsValueRecord, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_BAD_REQUEST|\OCP\AppFramework\Http::STATUS_FORBIDDEN|\OCP\AppFramework\Http::STATUS_NOT_FOUND|\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: User field value stored successfully
	 * 400: Invalid field value payload
	 * 401: Authenticated user is required
	 * 403: Field cannot be edited by the user
	 * 404: Field definition not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/me/values/{fieldDefinitionId}')]
	public function upsert(
		int $fieldDefinitionId,
		array|string|int|float|bool|null $value = null,
		?string $currentVisibility = null,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => $this->l10n->t('Authenticated user is required')], 401);
		}

		$definition = $this->fieldDefinitionService->findById($fieldDefinitionId);
		if ($definition === null || !$definition->getActive()) {
			return new DataResponse(['message' => $this->l10n->t('Field definition not found')], 404);
		}

		if (!$this->fieldAccessService->canEditValue($this->userId, $this->userId, $definition, false)) {
			return new DataResponse(['message' => $this->l10n->t('Field cannot be edited by the user')], 403);
		}

		if ($currentVisibility !== null && !$this->fieldAccessService->canChangeVisibility($this->userId, $this->userId, false)) {
			return new DataResponse(['message' => $this->l10n->t('Field visibility cannot be changed by the user')], 403);
		}

		try {
			$stored = $this->fieldValueService->upsert($definition, $this->userId, $value, $this->userId, $currentVisibility);

			return new DataResponse($this->fieldValueService->serializeForResponse($stored), 200);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], 400);
		}
	}

	/**
	 * Update the visibility of the authenticated user's stored value
	 *
	 * Change only the visibility of an already stored field value owned by the authenticated user.
	 *
	 * @param int $fieldDefinitionId Identifier of the field definition
	 * @param string $currentVisibility Visibility to apply to the stored value
	 * @return DataResponse<\OCP\AppFramework\Http::STATUS_OK, ProfileFieldsValueRecord, array{}>|DataResponse<\OCP\AppFramework\Http::STATUS_BAD_REQUEST|\OCP\AppFramework\Http::STATUS_FORBIDDEN|\OCP\AppFramework\Http::STATUS_NOT_FOUND|\OCP\AppFramework\Http::STATUS_UNAUTHORIZED, array{message: string}, array{}>
	 *
	 * 200: Field visibility updated successfully
	 * 400: Invalid visibility payload or value missing
	 * 401: Authenticated user is required
	 * 403: Field visibility cannot be changed by the user
	 * 404: Field definition not found
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/api/v1/me/values/{fieldDefinitionId}/visibility')]
	public function updateVisibility(
		int $fieldDefinitionId,
		string $currentVisibility,
	): DataResponse {
		if ($this->userId === null) {
			return new DataResponse(['message' => $this->l10n->t('Authenticated user is required')], 401);
		}

		$definition = $this->fieldDefinitionService->findById($fieldDefinitionId);
		if ($definition === null || !$definition->getActive()) {
			return new DataResponse(['message' => $this->l10n->t('Field definition not found')], 404);
		}

		if (!$this->fieldAccessService->canEditValue($this->userId, $this->userId, $definition, false)) {
			return new DataResponse(['message' => $this->l10n->t('Field cannot be edited by the user')], 403);
		}

		if (!$this->fieldAccessService->canChangeVisibility($this->userId, $this->userId, false)) {
			return new DataResponse(['message' => $this->l10n->t('Field visibility cannot be changed by the user')], 403);
		}

		try {
			$stored = $this->fieldValueService->updateVisibility($definition, $this->userId, $this->userId, $currentVisibility);

			return new DataResponse($this->fieldValueService->serializeForResponse($stored), 200);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], 400);
		}
	}
}
