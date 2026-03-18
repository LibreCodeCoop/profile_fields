<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;
use JsonException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueCreatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldVisibilityUpdatedEvent;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\EventDispatcher\IEventDispatcher;

class FieldValueService {
	private const SEARCH_OPERATOR_EQ = 'eq';
	private const SEARCH_OPERATOR_CONTAINS = 'contains';
	private const SEARCH_MAX_LIMIT = 100;

	public function __construct(
		private FieldValueMapper $fieldValueMapper,
		private IEventDispatcher $eventDispatcher,
	) {
	}

	/**
	 * @param array<string, mixed>|scalar|null $rawValue
	 */
	public function upsert(
		FieldDefinition $definition,
		string $userUid,
		array|string|int|float|bool|null $rawValue,
		string $updatedByUid,
		?string $currentVisibility = null,
		?DateTimeInterface $updatedAt = null,
	): FieldValue {
		$normalizedValue = $this->normalizeValue($definition, $rawValue);
		$valueJson = $this->encodeValue($normalizedValue);
		$visibility = $currentVisibility ?? $definition->getInitialVisibility();
		if (!FieldVisibility::isValid($visibility)) {
			throw new InvalidArgumentException('current_visibility is not supported');
		}

		$entity = $this->fieldValueMapper->findByFieldDefinitionIdAndUserUid($definition->getId(), $userUid) ?? new FieldValue();
		$previousValue = $entity->getId() === null ? null : $this->extractScalarValue($entity->getValueJson());
		$previousVisibility = $entity->getId() === null ? null : $entity->getCurrentVisibility();
		$valueChanged = $previousValue !== ($normalizedValue['value'] ?? null);
		$visibilityChanged = $previousVisibility !== null && $previousVisibility !== $visibility;
		$entity->setFieldDefinitionId($definition->getId());
		$entity->setUserUid($userUid);
		$entity->setValueJson($valueJson);
		$entity->setCurrentVisibility($visibility);
		$entity->setUpdatedByUid($updatedByUid);
		$entity->setUpdatedAt($this->asMutableDateTime($updatedAt));

		if ($entity->getId() === null) {
			$stored = $this->fieldValueMapper->insert($entity);
			$this->eventDispatcher->dispatchTyped(new ProfileFieldValueCreatedEvent(
				$this->buildWorkflowSubject($definition, $stored, $updatedByUid, null, null),
			));

			return $stored;
		}

		$stored = $this->fieldValueMapper->update($entity);
		if ($valueChanged) {
			$this->eventDispatcher->dispatchTyped(new ProfileFieldValueUpdatedEvent(
				$this->buildWorkflowSubject($definition, $stored, $updatedByUid, $previousValue, $previousVisibility),
			));
		}
		if ($visibilityChanged) {
			$this->eventDispatcher->dispatchTyped(new ProfileFieldVisibilityUpdatedEvent(
				$this->buildWorkflowSubject($definition, $stored, $updatedByUid, $previousValue, $previousVisibility),
			));
		}

		return $stored;
	}

	/**
	 * @param array<string, mixed>|scalar|null $rawValue
	 * @return array<string, mixed>
	 */
	public function normalizeValue(FieldDefinition $definition, array|string|int|float|bool|null $rawValue): array {
		$type = FieldType::from($definition->getType());

		if ($rawValue === null || $rawValue === '') {
			return ['value' => null];
		}

		return match ($type) {
			FieldType::TEXT => $this->normalizeTextValue($rawValue),
			FieldType::NUMBER => $this->normalizeNumberValue($rawValue),
			FieldType::SELECT => $this->normalizeSelectValue($rawValue, $definition),
		};
	}

	/**
	 * @return list<FieldValue>
	 */
	public function findByUserUid(string $userUid): array {
		return $this->fieldValueMapper->findByUserUid($userUid);
	}

	public function findByFieldDefinitionIdAndUserUid(int $fieldDefinitionId, string $userUid): ?FieldValue {
		return $this->fieldValueMapper->findByFieldDefinitionIdAndUserUid($fieldDefinitionId, $userUid);
	}

	/**
	 * @param array<string, mixed>|scalar|null $rawValue
	 * @return list<FieldValue>
	 */
	public function findByDefinitionAndRawValue(FieldDefinition $definition, array|string|int|float|bool|null $rawValue): array {
		$normalizedValue = $this->normalizeValue($definition, $rawValue);
		return $this->fieldValueMapper->findByFieldDefinitionIdAndValueJson(
			$definition->getId(),
			$this->encodeValue($normalizedValue),
		);
	}

	/**
	 * @param array<string, mixed>|scalar|null $rawValue
	 * @return array{total: int, matches: list<FieldValue>}
	 */
	public function searchByDefinition(
		FieldDefinition $definition,
		string $operator,
		array|string|int|float|bool|null $rawValue,
		int $limit,
		int $offset,
	): array {
		if ($limit < 1 || $limit > self::SEARCH_MAX_LIMIT) {
			throw new InvalidArgumentException(sprintf('limit must be between 1 and %d', self::SEARCH_MAX_LIMIT));
		}

		if ($offset < 0) {
			throw new InvalidArgumentException('offset must be greater than or equal to 0');
		}

		$normalizedOperator = strtolower(trim($operator));
		if (!in_array($normalizedOperator, [self::SEARCH_OPERATOR_EQ, self::SEARCH_OPERATOR_CONTAINS], true)) {
			throw new InvalidArgumentException('search operator is not supported');
		}

		$searchValue = $this->normalizeSearchValue($definition, $normalizedOperator, $rawValue);
		$fieldType = FieldType::from($definition->getType());
		$matches = array_values(array_filter(
			$this->fieldValueMapper->findByFieldDefinitionId($definition->getId()),
			fn (FieldValue $candidate): bool => $this->matchesSearchOperator(
				$fieldType,
				$this->decodeValue($candidate->getValueJson()),
				$searchValue,
				$normalizedOperator,
			),
		));

		return [
			'total' => count($matches),
			'matches' => array_slice($matches, $offset, $limit),
		];
	}

	public function updateVisibility(FieldDefinition $definition, string $userUid, string $updatedByUid, string $currentVisibility): FieldValue {
		if (!FieldVisibility::isValid($currentVisibility)) {
			throw new InvalidArgumentException('current_visibility is not supported');
		}

		$entity = $this->fieldValueMapper->findByFieldDefinitionIdAndUserUid($definition->getId(), $userUid);
		if ($entity === null) {
			throw new InvalidArgumentException('field value not found');
		}

		$previousValue = $this->extractScalarValue($entity->getValueJson());
		$previousVisibility = $entity->getCurrentVisibility();

		$entity->setCurrentVisibility($currentVisibility);
		$entity->setUpdatedByUid($updatedByUid);
		$entity->setUpdatedAt($this->asMutableDateTime());

		$stored = $this->fieldValueMapper->update($entity);
		$this->eventDispatcher->dispatchTyped(new ProfileFieldVisibilityUpdatedEvent(
			$this->buildWorkflowSubject($definition, $stored, $updatedByUid, $previousValue, $previousVisibility),
		));

		return $stored;
	}

	/**
	 * @return array{
	 *     id: int,
	 *     field_definition_id: int,
	 *     user_uid: string,
	 *     value: array<string, mixed>,
	 *     current_visibility: string,
	 *     updated_by_uid: string,
	 *     updated_at: string,
	 * }
	 */
	public function serializeForResponse(FieldValue $value): array {
		return [
			'id' => $value->getId(),
			'field_definition_id' => $value->getFieldDefinitionId(),
			'user_uid' => $value->getUserUid(),
			'value' => $this->decodeValue($value->getValueJson()),
			'current_visibility' => $value->getCurrentVisibility(),
			'updated_by_uid' => $value->getUpdatedByUid(),
			'updated_at' => $value->getUpdatedAt()->format(DATE_ATOM),
		];
	}

	/**
	 * @param array<string, mixed>|scalar $rawValue
	 * @return array{value: string}
	 */
	private function normalizeTextValue(array|string|int|float|bool $rawValue): array {
		if (is_array($rawValue)) {
			throw new InvalidArgumentException('text fields expect a scalar value');
		}

		return ['value' => trim((string)$rawValue)];
	}

	/**
	 * @param array<string, mixed>|scalar $rawValue
	 * @return array{value: string}
	 */
	private function normalizeSelectValue(array|string|int|float|bool $rawValue, FieldDefinition $definition): array {
		if (!is_string($rawValue)) {
			throw new InvalidArgumentException('select fields expect a string value');
		}

		$value = trim($rawValue);
		$options = json_decode($definition->getOptions() ?? '[]', true);
		if (!in_array($value, $options, true)) {
			throw new InvalidArgumentException(sprintf('"%s" is not a valid option for this field', $value));
		}

		return ['value' => $value];
	}

	/**
	 * @param array<string, mixed>|scalar $rawValue
	 * @return array{value: int|float}
	 */
	private function normalizeNumberValue(array|string|int|float|bool $rawValue): array {
		if (is_array($rawValue) || is_bool($rawValue) || !is_numeric($rawValue)) {
			throw new InvalidArgumentException('number fields expect a numeric value');
		}

		return ['value' => str_contains((string)$rawValue, '.') ? (float)$rawValue : (int)$rawValue];
	}

	/**
	 * @param array<string, mixed> $value
	 */
	private function encodeValue(array $value): string {
		try {
			return json_encode($value, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('value_json could not be encoded', 0, $exception);
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeValue(string $valueJson): array {
		try {
			$decoded = json_decode($valueJson, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new InvalidArgumentException('value_json could not be decoded', 0, $exception);
		}

		if (!is_array($decoded)) {
			throw new InvalidArgumentException('value_json must decode to an object payload');
		}

		return $decoded;
	}

	private function extractScalarValue(string $valueJson): string|int|float|bool|null {
		$decoded = $this->decodeValue($valueJson);
		$value = $decoded['value'] ?? null;

		return is_array($value) || is_object($value) ? null : $value;
	}

	private function buildWorkflowSubject(
		FieldDefinition $definition,
		FieldValue $value,
		string $actorUid,
		string|int|float|bool|null $previousValue,
		?string $previousVisibility,
	): ProfileFieldValueWorkflowSubject {
		return new ProfileFieldValueWorkflowSubject(
			userUid: $value->getUserUid(),
			actorUid: $actorUid,
			fieldDefinition: $definition,
			currentValue: $this->extractScalarValue($value->getValueJson()),
			previousValue: $previousValue,
			currentVisibility: $value->getCurrentVisibility(),
			previousVisibility: $previousVisibility,
		);
	}

	/**
	 * @param array<string, mixed>|scalar|null $rawValue
	 * @return array{value: mixed}
	 */
	private function normalizeSearchValue(FieldDefinition $definition, string $operator, array|string|int|float|bool|null $rawValue): array {
		if ($operator === self::SEARCH_OPERATOR_EQ) {
			return $this->normalizeValue($definition, $rawValue);
		}

		if (FieldType::from($definition->getType()) !== FieldType::TEXT) {
			throw new InvalidArgumentException('contains operator is only supported for text fields');
		}

		$normalized = $this->normalizeValue($definition, $rawValue);
		$value = $normalized['value'] ?? null;
		if (!is_string($value) || $value === '') {
			throw new InvalidArgumentException('contains operator requires a non-empty text value');
		}

		return ['value' => $value];
	}

	/**
	 * @param array<string, mixed> $candidateValue
	 * @param array{value: mixed} $searchValue
	 */
	private function matchesSearchOperator(FieldType $fieldType, array $candidateValue, array $searchValue, string $operator): bool {
		if ($operator === self::SEARCH_OPERATOR_EQ) {
			return ($candidateValue['value'] ?? null) === ($searchValue['value'] ?? null);
		}

		if ($fieldType !== FieldType::TEXT) {
			return false;
		}

		$candidateText = $candidateValue['value'] ?? null;
		$needle = $searchValue['value'] ?? null;
		if (!is_string($candidateText) || !is_string($needle)) {
			return false;
		}

		return str_contains(strtolower($candidateText), strtolower($needle));
	}

	private function asMutableDateTime(?DateTimeInterface $value = null): DateTime {
		if ($value instanceof DateTime) {
			return clone $value;
		}

		if ($value !== null) {
			return DateTime::createFromInterface($value);
		}

		return new DateTime();
	}
}
