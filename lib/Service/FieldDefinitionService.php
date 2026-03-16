<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use DateTime;
use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValueMapper;

class FieldDefinitionService {
	public function __construct(
		private FieldDefinitionMapper $fieldDefinitionMapper,
		private FieldValueMapper $fieldValueMapper,
		private FieldDefinitionValidator $validator,
	) {
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	public function create(array $definition): FieldDefinition {
		$validated = $this->validator->validate($definition);
		if ($this->fieldDefinitionMapper->findByFieldKey($validated['field_key']) !== null) {
			throw new InvalidArgumentException('field_key already exists');
		}

		$createdAt = $this->parseImportedDate($definition['created_at'] ?? null) ?? new DateTime();
		$updatedAt = $this->parseImportedDate($definition['updated_at'] ?? null) ?? clone $createdAt;
		$entity = new FieldDefinition();
		$entity->setFieldKey($validated['field_key']);
		$entity->setLabel($validated['label']);
		$entity->setType($validated['type']);
		$entity->setAdminOnly($validated['admin_only']);
		$entity->setUserEditable($validated['user_editable']);
		$entity->setUserVisible($validated['user_visible']);
		$entity->setInitialVisibility($validated['initial_visibility']);
		$entity->setSortOrder($validated['sort_order']);
		$entity->setActive($validated['active']);
		$entity->setCreatedAt($createdAt);
		$entity->setUpdatedAt($updatedAt);

		return $this->fieldDefinitionMapper->insert($entity);
	}

	/**
	 * @param array<string, mixed> $definition
	 */
	public function update(FieldDefinition $existing, array $definition): FieldDefinition {
		$validated = $this->validator->validate($definition + ['field_key' => $existing->getFieldKey()]);
		if (($definition['field_key'] ?? $existing->getFieldKey()) !== $existing->getFieldKey()) {
			throw new InvalidArgumentException('field_key cannot be changed');
		}

		if ($validated['type'] !== $existing->getType() && $this->fieldValueMapper->hasValuesForFieldDefinitionId($existing->getId())) {
			throw new InvalidArgumentException('type cannot be changed after values exist');
		}

		$existing->setLabel($validated['label']);
		$existing->setType($validated['type']);
		$existing->setAdminOnly($validated['admin_only']);
		$existing->setUserEditable($validated['user_editable']);
		$existing->setUserVisible($validated['user_visible']);
		$existing->setInitialVisibility($validated['initial_visibility']);
		$existing->setSortOrder($validated['sort_order']);
		$existing->setActive($validated['active']);
		$existing->setUpdatedAt($this->parseImportedDate($definition['updated_at'] ?? null) ?? new DateTime());

		return $this->fieldDefinitionMapper->update($existing);
	}

	private function parseImportedDate(mixed $value): ?DateTime {
		if (!is_string($value) || $value === '') {
			return null;
		}

		return new DateTime($value);
	}

	/**
	 * @return list<FieldDefinition>
	 */
	public function findAllOrdered(): array {
		return $this->fieldDefinitionMapper->findAllOrdered();
	}

	public function findById(int $id): ?FieldDefinition {
		return $this->fieldDefinitionMapper->findById($id);
	}

	public function findByFieldKey(string $fieldKey): ?FieldDefinition {
		return $this->fieldDefinitionMapper->findByFieldKey($fieldKey);
	}

	public function delete(int $id): ?FieldDefinition {
		$definition = $this->fieldDefinitionMapper->findById($id);
		if ($definition === null) {
			return null;
		}

		return $this->fieldDefinitionMapper->delete($definition);
	}

	/**
	 * @return list<FieldDefinition>
	 */
	public function findActiveOrdered(): array {
		return $this->fieldDefinitionMapper->findActiveOrdered();
	}
}
