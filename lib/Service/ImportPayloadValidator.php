<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCP\IUserManager;

class ImportPayloadValidator {
	private const SCHEMA_VERSION = 1;

	public function __construct(
		private FieldDefinitionValidator $fieldDefinitionValidator,
		private FieldDefinitionService $fieldDefinitionService,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{
	 *     schema_version: int,
	 *     definitions: list<array{
	 *         field_key: non-empty-string,
	 *         label: non-empty-string,
	 *         type: 'text'|'number',
	 *         admin_only: bool,
	 *         user_editable: bool,
	 *         user_visible: bool,
	 *         initial_visibility: 'private'|'users'|'public',
	 *         sort_order: int,
	 *         active: bool,
	 *     }>,
	 *     values: list<array{
	 *         field_key: non-empty-string,
	 *         user_uid: non-empty-string,
	 *         value: array{value: mixed},
	 *         current_visibility: 'private'|'users'|'public',
	 *         updated_by_uid: string,
	 *         updated_at: non-empty-string,
	 *     }>,
	 * }
	 */
	public function validate(array $payload): array {
		$schemaVersion = $payload['schema_version'] ?? null;
		if (!is_int($schemaVersion) || $schemaVersion !== self::SCHEMA_VERSION) {
			throw new InvalidArgumentException(sprintf('schema_version must be %d', self::SCHEMA_VERSION));
		}

		$definitions = $this->validateDefinitions($this->requireList($payload, 'definitions'));
		$values = $this->validateValues($this->requireList($payload, 'values'), $definitions);

		return [
			'schema_version' => $schemaVersion,
			'definitions' => array_values($definitions),
			'values' => $values,
		];
	}

	/**
	 * @param list<mixed> $definitions
	 * @return array<non-empty-string, array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number',
	 *     admin_only: bool,
	 *     user_editable: bool,
	 *     user_visible: bool,
	 *     initial_visibility: 'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 * }>
	 */
	private function validateDefinitions(array $definitions): array {
		$normalizedDefinitions = [];

		foreach ($definitions as $index => $definition) {
			if (!is_array($definition)) {
				throw new InvalidArgumentException(sprintf('definitions[%d] must be an object', $index));
			}

			$validatedDefinition = $this->fieldDefinitionValidator->validate($definition);
			$fieldKey = $validatedDefinition['field_key'];

			if (isset($normalizedDefinitions[$fieldKey])) {
				throw new InvalidArgumentException(sprintf('definitions[%d].field_key is duplicated', $index));
			}

			$existingDefinition = $this->fieldDefinitionService->findByFieldKey($fieldKey);
			if ($existingDefinition !== null && !$this->isCompatibleDefinition($existingDefinition, $validatedDefinition)) {
				throw new InvalidArgumentException(sprintf('definitions[%d].field_key conflicts with an incompatible existing definition', $index));
			}

			$normalizedDefinitions[$fieldKey] = $validatedDefinition;
		}

		return $normalizedDefinitions;
	}

	/**
	 * @param list<mixed> $values
	 * @param array<non-empty-string, array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number',
	 *     admin_only: bool,
	 *     user_editable: bool,
	 *     user_visible: bool,
	 *     initial_visibility: 'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 * }> $definitions
	 * @return list<array{
	 *     field_key: non-empty-string,
	 *     user_uid: non-empty-string,
	 *     value: array{value: mixed},
	 *     current_visibility: 'private'|'users'|'public',
	 *     updated_by_uid: string,
	 *     updated_at: non-empty-string,
	 * }>
	 */
	private function validateValues(array $values, array $definitions): array {
		$normalizedValues = [];
		$seenValueKeys = [];

		foreach ($values as $index => $value) {
			if (!is_array($value)) {
				throw new InvalidArgumentException(sprintf('values[%d] must be an object', $index));
			}

			$fieldKey = $this->requireNonEmptyString($value, 'field_key', sprintf('values[%d].field_key is required', $index));
			if (!isset($definitions[$fieldKey])) {
				throw new InvalidArgumentException(sprintf('values[%d].field_key references an unknown definition', $index));
			}

			$userUid = $this->requireNonEmptyString($value, 'user_uid', sprintf('values[%d].user_uid is required', $index));
			if (!$this->userManager->userExists($userUid)) {
				throw new InvalidArgumentException(sprintf('values[%d].user_uid does not exist in destination instance', $index));
			}

			$valuePayload = $value['value'] ?? null;
			if (!is_array($valuePayload) || !array_key_exists('value', $valuePayload)) {
				throw new InvalidArgumentException(sprintf('values[%d].value must be an object payload with a value key', $index));
			}

			$currentVisibility = $this->requireNonEmptyString($value, 'current_visibility', sprintf('values[%d].current_visibility is required', $index));
			if (!FieldVisibility::isValid($currentVisibility)) {
				throw new InvalidArgumentException(sprintf('values[%d].current_visibility is not supported', $index));
			}

			$updatedByUid = $this->requireString($value, 'updated_by_uid', sprintf('values[%d].updated_by_uid is required', $index));
			$updatedAt = $this->requireNonEmptyString($value, 'updated_at', sprintf('values[%d].updated_at is required', $index));
			$this->assertDate($updatedAt, sprintf('values[%d].updated_at must be a valid ISO-8601 datetime', $index));

			$compoundKey = $fieldKey . '\0' . $userUid;
			if (isset($seenValueKeys[$compoundKey])) {
				throw new InvalidArgumentException(sprintf('values[%d] duplicates field_key/user_uid pair', $index));
			}
			$seenValueKeys[$compoundKey] = true;

			$normalizedValues[] = [
				'field_key' => $fieldKey,
				'user_uid' => $userUid,
				'value' => ['value' => $valuePayload['value']],
				'current_visibility' => $currentVisibility,
				'updated_by_uid' => $updatedByUid,
				'updated_at' => $updatedAt,
			];
		}

		return $normalizedValues;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return list<mixed>
	 */
	private function requireList(array $payload, string $key): array {
		$value = $payload[$key] ?? null;
		if (!is_array($value) || !array_is_list($value)) {
			throw new InvalidArgumentException(sprintf('%s must be a list', $key));
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function requireNonEmptyString(array $payload, string $key, string $message): string {
		$value = trim((string)($payload[$key] ?? ''));
		if ($value === '') {
			throw new InvalidArgumentException($message);
		}

		return $value;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function requireString(array $payload, string $key, string $message): string {
		if (!array_key_exists($key, $payload) || !is_string($payload[$key])) {
			throw new InvalidArgumentException($message);
		}

		return $payload[$key];
	}

	private function assertDate(string $value, string $message): void {
		try {
			new DateTimeImmutable($value);
		} catch (\Exception) {
			throw new InvalidArgumentException($message);
		}
	}

	/**
	 * @param array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number',
	 *     admin_only: bool,
	 *     user_editable: bool,
	 *     user_visible: bool,
	 *     initial_visibility: 'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 * } $definition
	 */
	private function isCompatibleDefinition(FieldDefinition $existingDefinition, array $definition): bool {
		return $existingDefinition->getType() === $definition['type']
			&& $existingDefinition->getAdminOnly() === $definition['admin_only']
			&& $existingDefinition->getUserEditable() === $definition['user_editable']
			&& $existingDefinition->getUserVisible() === $definition['user_visible']
			&& $existingDefinition->getInitialVisibility() === $definition['initial_visibility'];
	}
}