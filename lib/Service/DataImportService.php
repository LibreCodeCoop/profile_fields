<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use DateTime;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCP\IDBConnection;

class DataImportService {
	public function __construct(
		private ImportPayloadValidator $importPayloadValidator,
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueService $fieldValueService,
		private IDBConnection $connection,
	) {
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{
	 *     created_definitions: int,
	 *     updated_definitions: int,
	 *     skipped_definitions: int,
	 *     created_values: int,
	 *     updated_values: int,
	 *     skipped_values: int,
	 * }
	 */
	public function import(array $payload, bool $dryRun = false): array {
		$normalizedPayload = $this->importPayloadValidator->validate($payload);
		$summary = [
			'created_definitions' => 0,
			'updated_definitions' => 0,
			'skipped_definitions' => 0,
			'created_values' => 0,
			'updated_values' => 0,
			'skipped_values' => 0,
		];

		if ($dryRun) {
			$this->collectDefinitionSummary($normalizedPayload['definitions'], $summary);
			$this->collectValueSummary($normalizedPayload['values'], $summary);
			return $summary;
		}

		$this->connection->beginTransaction();

		try {
			$definitionsByFieldKey = $this->persistDefinitions($normalizedPayload['definitions'], $summary);
			$this->persistValues($normalizedPayload['values'], $definitionsByFieldKey, $summary);
			$this->connection->commit();
		} catch (\Throwable $throwable) {
			$this->connection->rollBack();
			throw $throwable;
		}

		return $summary;
	}

	/**
	 * @param list<array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number'|'boolean'|'date'|'url'|'select'|'multiselect',
	 *     edit_policy: 'admins'|'users',
	 *     exposure_policy: 'hidden'|'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 *     created_at?: non-empty-string,
	 *     updated_at?: non-empty-string,
	 * }> $definitions
	 * @param array{
	 *     created_definitions: int,
	 *     updated_definitions: int,
	 *     skipped_definitions: int,
	 *     created_values: int,
	 *     updated_values: int,
	 *     skipped_values: int,
	 * } $summary
	 */
	private function collectDefinitionSummary(array $definitions, array &$summary): void {
		foreach ($definitions as $definition) {
			$existingDefinition = $this->fieldDefinitionService->findByFieldKey($definition['field_key']);
			if ($existingDefinition === null) {
				$summary['created_definitions']++;
				continue;
			}

			if ($this->definitionNeedsUpdate($existingDefinition, $definition)) {
				$summary['updated_definitions']++;
				continue;
			}

			$summary['skipped_definitions']++;
		}
	}

	/**
	 * @param list<array{
	 *     field_key: non-empty-string,
	 *     user_uid: non-empty-string,
	 *     value: array{value: mixed},
	 *     current_visibility: 'private'|'users'|'public',
	 *     updated_by_uid: string,
	 *     updated_at: non-empty-string,
	 * }> $values
	 * @param array{
	 *     created_definitions: int,
	 *     updated_definitions: int,
	 *     skipped_definitions: int,
	 *     created_values: int,
	 *     updated_values: int,
	 *     skipped_values: int,
	 * } $summary
	 */
	private function collectValueSummary(array $values, array &$summary): void {
		foreach ($values as $value) {
			$definition = $this->fieldDefinitionService->findByFieldKey($value['field_key']);
			if ($definition === null) {
				$summary['created_values']++;
				continue;
			}

			$existingValue = $this->fieldValueService->findByFieldDefinitionIdAndUserUid($definition->getId(), $value['user_uid']);
			if ($existingValue === null) {
				$summary['created_values']++;
				continue;
			}

			if ($this->valueNeedsUpdate($existingValue, $value)) {
				$summary['updated_values']++;
				continue;
			}

			$summary['skipped_values']++;
		}
	}

	/**
	 * @param list<array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number'|'boolean'|'date'|'url'|'select'|'multiselect',
	 *     edit_policy: 'admins'|'users',
	 *     exposure_policy: 'hidden'|'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 *     created_at?: non-empty-string,
	 *     updated_at?: non-empty-string,
	 * }> $definitions
	 * @param array{
	 *     created_definitions: int,
	 *     updated_definitions: int,
	 *     skipped_definitions: int,
	 *     created_values: int,
	 *     updated_values: int,
	 *     skipped_values: int,
	 * } $summary
	 * @return array<non-empty-string, FieldDefinition>
	 */
	private function persistDefinitions(array $definitions, array &$summary): array {
		$definitionsByFieldKey = [];

		foreach ($definitions as $definition) {
			$existingDefinition = $this->fieldDefinitionService->findByFieldKey($definition['field_key']);
			if ($existingDefinition === null) {
				$definitionsByFieldKey[$definition['field_key']] = $this->fieldDefinitionService->create($definition);
				$summary['created_definitions']++;
				continue;
			}

			if ($this->definitionNeedsUpdate($existingDefinition, $definition)) {
				$existingDefinition = $this->fieldDefinitionService->update($existingDefinition, $definition);
				$summary['updated_definitions']++;
			} else {
				$summary['skipped_definitions']++;
			}

			$definitionsByFieldKey[$definition['field_key']] = $existingDefinition;
		}

		return $definitionsByFieldKey;
	}

	/**
	 * @param list<array{
	 *     field_key: non-empty-string,
	 *     user_uid: non-empty-string,
	 *     value: array{value: mixed},
	 *     current_visibility: 'private'|'users'|'public',
	 *     updated_by_uid: string,
	 *     updated_at: non-empty-string,
	 * }> $values
	 * @param array<non-empty-string, FieldDefinition> $definitionsByFieldKey
	 * @param array{
	 *     created_definitions: int,
	 *     updated_definitions: int,
	 *     skipped_definitions: int,
	 *     created_values: int,
	 *     updated_values: int,
	 *     skipped_values: int,
	 * } $summary
	 */
	private function persistValues(array $values, array $definitionsByFieldKey, array &$summary): void {
		foreach ($values as $value) {
			$definition = $definitionsByFieldKey[$value['field_key']];
			$existingValue = $this->fieldValueService->findByFieldDefinitionIdAndUserUid($definition->getId(), $value['user_uid']);

			if ($existingValue === null) {
				$this->fieldValueService->upsert(
					$definition,
					$value['user_uid'],
					$value['value']['value'],
					$value['updated_by_uid'],
					$value['current_visibility'],
					new DateTime($value['updated_at']),
				);
				$summary['created_values']++;
				continue;
			}

			if ($this->valueNeedsUpdate($existingValue, $value)) {
				$this->fieldValueService->upsert(
					$definition,
					$value['user_uid'],
					$value['value']['value'],
					$value['updated_by_uid'],
					$value['current_visibility'],
					new DateTime($value['updated_at']),
				);
				$summary['updated_values']++;
				continue;
			}

			$summary['skipped_values']++;
		}
	}

	/**
	 * @param array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number'|'boolean'|'date'|'url'|'select'|'multiselect',
	 *     edit_policy: 'admins'|'users',
	 *     exposure_policy: 'hidden'|'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 *     created_at?: non-empty-string,
	 *     updated_at?: non-empty-string,
	 * } $definition
	 */
	private function definitionNeedsUpdate(FieldDefinition $existingDefinition, array $definition): bool {
		return $existingDefinition->getLabel() !== $definition['label']
			|| $existingDefinition->getSortOrder() !== $definition['sort_order']
			|| $existingDefinition->getActive() !== $definition['active']
			|| (($definition['updated_at'] ?? null) !== null && $existingDefinition->getUpdatedAt()->format(DATE_ATOM) !== $definition['updated_at']);
	}

	/**
	 * @param array{
	 *     field_key: non-empty-string,
	 *     user_uid: non-empty-string,
	 *     value: array{value: mixed},
	 *     current_visibility: 'private'|'users'|'public',
	 *     updated_by_uid: string,
	 *     updated_at: non-empty-string,
	 * } $value
	 */
	private function valueNeedsUpdate(FieldValue $existingValue, array $value): bool {
		$serializedValue = $this->fieldValueService->serializeForResponse($existingValue);

		return $serializedValue['value'] !== $value['value']
			|| $serializedValue['current_visibility'] !== $value['current_visibility']
			|| $serializedValue['updated_by_uid'] !== $value['updated_by_uid']
			|| $serializedValue['updated_at'] !== $value['updated_at'];
	}
}
