<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use InvalidArgumentException;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;

class FieldDefinitionValidator {
	/**
	 * @param array{
	 *     field_key?: string,
	 *     label?: string,
	 *     type?: string,
	 *     admin_only?: bool,
	 *     user_editable?: bool,
	 *     user_visible?: bool,
	 *     initial_visibility?: string,
	 *     sort_order?: int,
	 *     active?: bool,
	 *     options?: list<string>,
	 * } $definition
	 * @return array{
	 *     field_key: non-empty-string,
	 *     label: non-empty-string,
	 *     type: 'text'|'number'|'select',
	 *     admin_only: bool,
	 *     user_editable: bool,
	 *     user_visible: bool,
	 *     initial_visibility: 'private'|'users'|'public',
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

		$visibility = (string)($definition['initial_visibility'] ?? FieldVisibility::PRIVATE->value);
		if (!FieldVisibility::isValid($visibility)) {
			throw new InvalidArgumentException('initial_visibility is not supported');
		}

		$adminOnly = (bool)($definition['admin_only'] ?? false);
		$userEditable = (bool)($definition['user_editable'] ?? false);
		$userVisible = (bool)($definition['user_visible'] ?? true);
		if ($adminOnly && $userEditable) {
			throw new InvalidArgumentException('admin_only and user_editable cannot both be enabled');
		}
		if (!$userVisible && $userEditable) {
			throw new InvalidArgumentException('user_editable cannot be enabled when the field is hidden from users');
		}

		$options = $this->validateOptions($type, $definition['options'] ?? null);

		return [
			'field_key' => $fieldKey,
			'label' => $label,
			'type' => $type,
			'admin_only' => $adminOnly,
			'user_editable' => $userEditable,
			'user_visible' => $userVisible,
			'initial_visibility' => $visibility,
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
		if ($type !== FieldType::SELECT->value) {
			return null;
		}

		if (!is_array($options) || count($options) === 0) {
			throw new InvalidArgumentException('select fields require at least one option');
		}

		foreach ($options as $option) {
			if (!is_string($option) || trim($option) === '') {
				throw new InvalidArgumentException('each option must be a non-empty string');
			}
		}

		return array_values($options);
	}

	/**
	 * @param array{
	 *     field_key?: string,
	 *     label?: string,
	 *     type?: string,
	 *     admin_only?: bool,
	 *     user_editable?: bool,
	 *     user_visible?: bool,
	 *     initial_visibility?: string,
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
