<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;

class FieldDefinitionValidator {
	/**
	 * @param array{
	 *     field_key?: string,
	 *     label?: string,
	 *     type?: string,
	 *     edit_policy?: string,
	 *     exposure_policy?: string,
	 *     sort_order?: int,
	 *     active?: bool,
	 *     options?: list<string>,
	 * } $definition
	 * @return array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number'|'boolean'|'date'|'select'|'multiselect',
	 *     edit_policy: 'admins'|'users',
	 *     exposure_policy: 'hidden'|'private'|'users'|'public',
	 *     sort_order: int,
	 *     active: bool,
	 *     options: list<string>|null,
	 * }
	 */
	public function validate(array $definition): array {
		$fieldKey = $this->requireString($definition, 'field_key');
		if (!preg_match('/^[a-z0-9_]+$/', $fieldKey)) {
			throw new InvalidArgumentException('field_key must contain only lowercase letters, numbers and underscores');
		}

		$label = $this->requireString($definition, 'label');
		$type = $this->requireString($definition, 'type');
		if (!FieldType::isValid($type)) {
			throw new InvalidArgumentException('type is not supported');
		}

		$editPolicy = (string)($definition['edit_policy'] ?? FieldEditPolicy::USERS->value);
		if (!FieldEditPolicy::isValid($editPolicy)) {
			throw new InvalidArgumentException('edit_policy is not supported');
		}

		$exposurePolicy = (string)($definition['exposure_policy'] ?? FieldExposurePolicy::PRIVATE->value);
		if (!FieldExposurePolicy::isValid($exposurePolicy)) {
			throw new InvalidArgumentException('exposure_policy is not supported');
		}

		$options = $this->validateOptions($type, $definition['options'] ?? null);

		return [
			'field_key' => $fieldKey,
			'label' => $label,
			'type' => $type,
			'edit_policy' => $editPolicy,
			'exposure_policy' => $exposurePolicy,
			'sort_order' => (int)($definition['sort_order'] ?? 0),
			'active' => (bool)($definition['active'] ?? true),
			'options' => $options,
		];
	}

	/**
	 * @param mixed $options
	 * @return list<string>|null
	 */
	private function validateOptions(string $type, mixed $options): ?array {
		$isSelectLike = in_array($type, [FieldType::SELECT->value, FieldType::MULTISELECT->value], true);
		if (!$isSelectLike) {
			return null;
		}

		if (!is_array($options) || count($options) === 0) {
			throw new InvalidArgumentException($type . ' fields require at least one option');
		}

		$normalized = [];
		foreach ($options as $option) {
			if (!is_string($option) || trim($option) === '') {
				throw new InvalidArgumentException('each option must be a non-empty string');
			}
			$normalized[] = trim($option);
		}

		$deduplicated = array_values(array_unique($normalized));
		if (count($deduplicated) !== count($normalized)) {
			throw new InvalidArgumentException('options must not contain duplicate values');
		}

		return $deduplicated;
	}

	/**
	 * @param array{
	 *     field_key?: string,
	 *     label?: string,
	 *     type?: string,
	 *     edit_policy?: string,
	 *     exposure_policy?: string,
	 *     sort_order?: int,
	 *     active?: bool,
	 *     options?: list<string>,
	 * } $definition
	 */
	private function requireString(array $definition, string $key): string {
		$value = trim((string)($definition[$key] ?? ''));
		if ($value === '') {
			throw new InvalidArgumentException($key . ' is required');
		}
		return $value;
	}
}
