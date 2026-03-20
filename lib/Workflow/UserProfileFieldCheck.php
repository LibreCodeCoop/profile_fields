<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use InvalidArgumentException;
use JsonException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserSession;
use OCP\WorkflowEngine\ICheck;
use OCP\WorkflowEngine\IManager;

class UserProfileFieldCheck implements ICheck {
	private const OPERATOR_IS_SET = 'is-set';
	private const OPERATOR_IS_NOT_SET = '!is-set';
	private const TEXT_OPERATORS = [
		self::OPERATOR_IS_SET,
		self::OPERATOR_IS_NOT_SET,
		'is',
		'!is',
		'contains',
		'!contains',
	];
	private const NUMBER_OPERATORS = [
		self::OPERATOR_IS_SET,
		self::OPERATOR_IS_NOT_SET,
		'is',
		'!is',
		'less',
		'!greater',
		'greater',
		'!less',
	];
	private const DATE_OPERATORS = [
		self::OPERATOR_IS_SET,
		self::OPERATOR_IS_NOT_SET,
		'is',
		'!is',
		'less',
		'!greater',
		'greater',
		'!less',
	];
	private const SELECT_OPERATORS = [
		self::OPERATOR_IS_SET,
		self::OPERATOR_IS_NOT_SET,
		'is',
		'!is',
	];

	public function __construct(
		private IUserSession $userSession,
		private IL10N $l10n,
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueService $fieldValueService,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function executeCheck($operator, $value) {
		try {
			$workflowSubject = $this->workflowSubjectContext->get();
			$config = $this->parseConfig((string)$value);
			$definition = $this->resolveDefinition($config['field_key']);
			if ($definition === null || !$this->isOperatorSupported($definition, (string)$operator)) {
				return false;
			}

			$userUid = $workflowSubject?->getUserUid();
			if ($userUid === null) {
				$user = $this->userSession->getUser();
				if (!$user instanceof IUser) {
					return $operator === self::OPERATOR_IS_NOT_SET;
				}

				$userUid = $user->getUID();
			}

			if ($userUid === '') {
				return $operator === self::OPERATOR_IS_NOT_SET;
			}

			$storedValue = $this->fieldValueService->findByFieldDefinitionIdAndUserUid($definition->getId(), $userUid);
			$actualValue = $this->extractActualValue($storedValue);

			return $this->evaluate($definition, (string)$operator, $config['value'], $actualValue);
		} catch (\Throwable) {
			return false;
		}
	}

	#[\Override]
	public function validateCheck($operator, $value) {
		$config = $this->parseConfig((string)$value);
		$definition = $this->resolveDefinition($config['field_key']);
		if ($definition === null) {
			throw new \UnexpectedValueException($this->l10n->t('The selected profile field does not exist'), 2);
		}

		if (!$this->isOperatorSupported($definition, (string)$operator)) {
			throw new \UnexpectedValueException($this->l10n->t('The selected operator is not supported for this profile field'), 3);
		}

		if ($this->operatorRequiresValue((string)$operator)) {
			try {
				if (FieldType::from($definition->getType()) === FieldType::MULTISELECT) {
					$this->normalizeExpectedMultiSelectOperand($definition, $config['value']);
				} else {
					$this->fieldValueService->normalizeValue($definition, $config['value']);
				}
			} catch (InvalidArgumentException $exception) {
				throw new \UnexpectedValueException($this->l10n->t('The configured comparison value is invalid'), 4, $exception);
			}
		}
	}

	#[\Override]
	public function supportedEntities(): array {
		return [];
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	/**
	 * @return array{field_key: string, value: string|int|float|bool|null}
	 */
	private function parseConfig(string $value): array {
		try {
			$decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new \UnexpectedValueException($this->l10n->t('The workflow check configuration is invalid'), 1, $exception);
		}

		if (!is_array($decoded) || !is_string($decoded['field_key'] ?? null) || trim($decoded['field_key']) === '') {
			throw new \UnexpectedValueException($this->l10n->t('The workflow check configuration is invalid'), 1);
		}

		$valueCandidate = $decoded['value'] ?? null;
		if (is_array($valueCandidate) || is_object($valueCandidate)) {
			throw new \UnexpectedValueException($this->l10n->t('The workflow check configuration is invalid'), 1);
		}

		return [
			'field_key' => trim($decoded['field_key']),
			'value' => $valueCandidate,
		];
	}

	private function resolveDefinition(string $fieldKey): ?FieldDefinition {
		$definition = $this->fieldDefinitionService->findByFieldKey($fieldKey);

		if ($definition === null || !$definition->getActive()) {
			return null;
		}

		return $definition;
	}

	private function isOperatorSupported(FieldDefinition $definition, string $operator): bool {
		$operators = match (FieldType::from($definition->getType())) {
			FieldType::TEXT => self::TEXT_OPERATORS,
			FieldType::NUMBER => self::NUMBER_OPERATORS,
			FieldType::DATE => self::DATE_OPERATORS,
			FieldType::SELECT => self::SELECT_OPERATORS,
			FieldType::MULTISELECT => self::SELECT_OPERATORS,
		};

		return in_array($operator, $operators, true);
	}

	private function operatorRequiresValue(string $operator): bool {
		return !in_array($operator, [self::OPERATOR_IS_SET, self::OPERATOR_IS_NOT_SET], true);
	}

	/**
	 * @return string|int|float|bool|list<string>|null
	 */
	private function extractActualValue(?FieldValue $value): string|int|float|bool|array|null {
		if ($value === null) {
			return null;
		}

		$serialized = $this->fieldValueService->serializeForResponse($value);
		$payload = $serialized['value']['value'] ?? null;

		if (is_array($payload) && array_is_list($payload)) {
			$normalized = [];
			foreach ($payload as $candidate) {
				if (is_string($candidate)) {
					$normalized[] = $candidate;
				}
			}

			return $normalized;
		}

		return is_object($payload) ? null : $payload;
	}

	/**
	 * @param string|int|float|bool|list<string>|null $actualValue
	 */
	private function evaluate(FieldDefinition $definition, string $operator, string|int|float|bool|null $expectedRawValue, string|int|float|bool|array|null $actualValue): bool {
		$isSet = $actualValue !== null
			&& $actualValue !== ''
			&& (!is_array($actualValue) || count($actualValue) > 0);
		if ($operator === self::OPERATOR_IS_SET) {
			return $isSet;
		}
		if ($operator === self::OPERATOR_IS_NOT_SET) {
			return !$isSet;
		}
		if (!$isSet) {
			return false;
		}

		$fieldType = FieldType::from($definition->getType());

		if ($fieldType === FieldType::MULTISELECT) {
			if (!is_array($actualValue)) {
				return false;
			}

			$expectedValue = $this->normalizeExpectedMultiSelectOperand($definition, $expectedRawValue);

			return $this->evaluateMultiSelectOperator($operator, $expectedValue, $actualValue);
		}

		$normalizedExpected = $this->fieldValueService->normalizeValue($definition, $expectedRawValue);
		$expectedValue = $normalizedExpected['value'] ?? null;

		return match ($fieldType) {
			FieldType::TEXT,
			FieldType::SELECT => $this->evaluateTextOperator($operator, (string)$expectedValue, (string)$actualValue),
			FieldType::NUMBER => $this->evaluateNumberOperator(
				$operator,
				$this->normalizeNumericComparisonOperand($expectedValue),
				$this->normalizeNumericComparisonOperand($actualValue),
			),
			FieldType::DATE => $this->evaluateDateOperator(
				$operator,
				$this->normalizeDateComparisonOperand($expectedValue),
				$this->normalizeDateComparisonOperand($actualValue),
			),
			FieldType::MULTISELECT => false,
		};
	}

	/**
	 * @param list<string> $actualValues
	 */
	private function evaluateMultiSelectOperator(string $operator, string $expectedValue, array $actualValues): bool {
		$contains = in_array($expectedValue, $actualValues, true);

		return match ($operator) {
			'is' => $contains,
			'!is' => !$contains,
			default => false,
		};
	}

	private function normalizeExpectedMultiSelectOperand(FieldDefinition $definition, string|int|float|bool|null $expectedRawValue): string {
		if (!is_string($expectedRawValue)) {
			throw new InvalidArgumentException('multiselect comparison value must be one configured option');
		}

		$value = trim($expectedRawValue);
		$options = json_decode($definition->getOptions() ?? '[]', true);
		if ($value === '' || !is_array($options) || !in_array($value, $options, true)) {
			throw new InvalidArgumentException('multiselect comparison value must be one configured option');
		}

		return $value;
	}

	private function normalizeNumericComparisonOperand(string|int|float|bool|null $value): int|float {
		if (is_int($value) || is_float($value)) {
			return $value;
		}

		return str_contains((string)$value, '.') ? (float)$value : (int)$value;
	}

	private function normalizeDateComparisonOperand(string|int|float|bool|null $value): int {
		if (!is_string($value)) {
			throw new InvalidArgumentException('date comparison value must be a valid YYYY-MM-DD string');
		}

		$date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		if ($date === false || $date->format('Y-m-d') !== $value) {
			throw new InvalidArgumentException('date comparison value must be a valid YYYY-MM-DD string');
		}

		return $date->getTimestamp();
	}

	private function evaluateTextOperator(string $operator, string $expectedValue, string $actualValue): bool {
		return match ($operator) {
			'is' => $actualValue === $expectedValue,
			'!is' => $actualValue !== $expectedValue,
			'contains' => $expectedValue !== '' && mb_stripos($actualValue, $expectedValue) !== false,
			'!contains' => $expectedValue === '' || mb_stripos($actualValue, $expectedValue) === false,
			default => false,
		};
	}

	private function evaluateNumberOperator(string $operator, int|float $expectedValue, int|float $actualValue): bool {
		return match ($operator) {
			'is' => $actualValue === $expectedValue,
			'!is' => $actualValue !== $expectedValue,
			'less' => $actualValue < $expectedValue,
			'!greater' => $actualValue <= $expectedValue,
			'greater' => $actualValue > $expectedValue,
			'!less' => $actualValue >= $expectedValue,
			default => false,
		};
	}

	private function evaluateDateOperator(string $operator, int $expectedValue, int $actualValue): bool {
		return match ($operator) {
			'is' => $actualValue === $expectedValue,
			'!is' => $actualValue !== $expectedValue,
			'less' => $actualValue < $expectedValue,
			'!greater' => $actualValue <= $expectedValue,
			'greater' => $actualValue > $expectedValue,
			'!less' => $actualValue >= $expectedValue,
			default => false,
		};
	}
}
