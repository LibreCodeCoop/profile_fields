<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Workflow;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Workflow\ProfileFieldValueEntity;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCA\ProfileFields\Workflow\UserProfileFieldCheck;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\WorkflowEngine\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserProfileFieldCheckTest extends TestCase {
	private IUserSession&MockObject $userSession;
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueMapper&MockObject $fieldValueMapper;
	private ProfileFieldValueSubjectContext $workflowSubjectContext;
	private UserProfileFieldCheck $check;

	protected function setUp(): void {
		parent::setUp();

		$this->userSession = $this->createMock(IUserSession::class);
		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->workflowSubjectContext = new ProfileFieldValueSubjectContext();

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters));

		$this->check = new UserProfileFieldCheck(
			$this->userSession,
			$l10n,
			$this->fieldDefinitionService,
			new FieldValueService($this->fieldValueMapper, $this->createMock(IEventDispatcher::class), $l10n),
			$this->workflowSubjectContext,
		);
	}

	public function testSupportedEntitiesIsUniversal(): void {
		$this->assertSame([], $this->check->supportedEntities());
	}

	public function testIsAvailableOnlyForAdminScope(): void {
		$this->assertTrue($this->check->isAvailableForScope(IManager::SCOPE_ADMIN));
		$this->assertFalse($this->check->isAvailableForScope(IManager::SCOPE_USER));
	}

	public function testValidateCheckRejectsInvalidConfigurationPayload(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The workflow check configuration is invalid');

		$this->check->validateCheck('is', '{not-json');
	}

	public function testValidateCheckRejectsUnknownFieldKey(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('unknown_field')
			->willReturn(null);

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The selected profile field does not exist');

		$this->check->validateCheck('is', $this->encodeConfig('unknown_field', 'LATAM'));
	}

	public function testValidateCheckRejectsContainsForNumberField(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('score')
			->willReturn($this->buildDefinition(7, 'score', FieldType::NUMBER->value));

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The selected operator is not supported for this profile field');

		$this->check->validateCheck('contains', $this->encodeConfig('score', '9'));
	}

	public function testExecuteCheckMatchesTextContainsCaseInsensitive(): void {
		$definition = $this->buildDefinition(7, 'region', FieldType::TEXT->value);
		$value = $this->buildStoredValue(7, 'alice', '{"value":"Ops LATAM"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('region')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('contains', $this->encodeConfig('region', 'latam')));
	}

	public function testExecuteCheckMatchesNumericComparison(): void {
		$definition = $this->buildDefinition(7, 'score', FieldType::NUMBER->value);
		$value = $this->buildStoredValue(7, 'alice', '{"value":9.5}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('score')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('greater', $this->encodeConfig('score', '9')));
	}

	public function testExecuteCheckMatchesDateComparison(): void {
		$definition = $this->buildDefinition(7, 'start_date', FieldType::DATE->value);
		$value = $this->buildStoredValue(7, 'alice', '{"value":"2026-03-20"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('start_date')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('greater', $this->encodeConfig('start_date', '2026-03-19')));
	}

	public function testValidateCheckRejectsContainsForDateField(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('start_date')
			->willReturn($this->buildDefinition(7, 'start_date', FieldType::DATE->value));

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The selected operator is not supported for this profile field');

		$this->check->validateCheck('contains', $this->encodeConfig('start_date', '2026-03-20'));
	}

	public function testExecuteCheckMatchesBooleanExactValue(): void {
		$definition = $this->buildDefinition(7, 'is_manager', FieldType::BOOLEAN->value);
		$value = $this->buildStoredValue(7, 'alice', '{"value":true}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('is_manager')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('is', $this->encodeConfig('is_manager', true)));
	}

	public function testValidateCheckRejectsContainsForBooleanField(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('is_manager')
			->willReturn($this->buildDefinition(7, 'is_manager', FieldType::BOOLEAN->value));

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The selected operator is not supported for this profile field');

		$this->check->validateCheck('contains', $this->encodeConfig('is_manager', true));
	}

	public function testExecuteCheckMatchesUrlContains(): void {
		$definition = $this->buildDefinition(8, 'website', FieldType::URL->value);
		$value = $this->buildStoredValue(8, 'alice', '{"value":"https://example.com/page"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('website')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(8, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('contains', $this->encodeConfig('website', 'example.com')));
	}

	public function testValidateCheckAcceptsContainsForUrlField(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('website')
			->willReturn($this->buildDefinition(8, 'website', FieldType::URL->value));

		// Should not throw
		$this->check->validateCheck('contains', $this->encodeConfig('website', 'example.com'));
		$this->addToAssertionCount(1);
	}

	public function testExecuteCheckMatchesEmailContains(): void {
		$definition = $this->buildDefinition(9, 'work_email', FieldType::EMAIL->value);
		$value = $this->buildStoredValue(9, 'alice', '{"value":"alice@example.com"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('work_email')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(9, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('contains', $this->encodeConfig('work_email', '@example.com')));
	}

	public function testValidateCheckAcceptsContainsForEmailField(): void {
		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('work_email')
			->willReturn($this->buildDefinition(9, 'work_email', FieldType::EMAIL->value));

		// Should not throw
		$this->check->validateCheck('contains', $this->encodeConfig('work_email', '@example.com'));
		$this->addToAssertionCount(1);
	}

	public function testExecuteCheckTreatsMissingValueAsNotSet(): void {
		$definition = $this->buildDefinition(7, 'region', FieldType::TEXT->value);

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('region')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn(null);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('!is-set', $this->encodeConfig('region', null)));
	}

	public function testExecuteCheckReturnsFalseWithoutAuthenticatedUser(): void {
		$this->userSession->method('getUser')->willReturn(null);

		$this->assertFalse($this->check->executeCheck('is', $this->encodeConfig('region', 'LATAM')));
	}

	public function testExecuteCheckUsesEntitySubjectUserWhenAvailable(): void {
		$definition = $this->buildDefinition(7, 'region', FieldType::TEXT->value);
		$value = $this->buildStoredValue(7, 'target-user', '{"value":"LATAM"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('region')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'target-user')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('admin'));

		$entityL10n = $this->createMock(IL10N::class);
		$entityL10n->method('t')->willReturnArgument(0);
		$entityUrlGenerator = $this->createMock(IURLGenerator::class);
		$entity = new ProfileFieldValueEntity($entityL10n, $entityUrlGenerator, $this->workflowSubjectContext);
		$entity->prepareRuleMatcher($this->createMock(\OCP\WorkflowEngine\IRuleMatcher::class), 'workflow-event', new \OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent(new ProfileFieldValueWorkflowSubject(
			userUid: 'target-user',
			actorUid: 'admin',
			fieldDefinition: $definition,
			currentValue: 'LATAM',
			previousValue: 'EMEA',
			currentVisibility: 'users',
			previousVisibility: 'users',
		)));

		$this->assertTrue($this->check->executeCheck('is', $this->encodeConfig('region', 'LATAM')));
	}

	public function testExecuteCheckMatchesSelectExactValue(): void {
		$definition = $this->buildDefinition(7, 'contract_type', FieldType::SELECT->value);
		$definition->setOptions(json_encode(['CLT', 'PJ', 'Cooperado']));
		$value = $this->buildStoredValue(7, 'alice', '{"value":"PJ"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('contract_type')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('is', $this->encodeConfig('contract_type', 'PJ')));
	}

	public function testExecuteCheckDoesNotMatchSelectDifferentValue(): void {
		$definition = $this->buildDefinition(7, 'contract_type', FieldType::SELECT->value);
		$definition->setOptions(json_encode(['CLT', 'PJ', 'Cooperado']));
		$value = $this->buildStoredValue(7, 'alice', '{"value":"PJ"}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('contract_type')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertFalse($this->check->executeCheck('is', $this->encodeConfig('contract_type', 'CLT')));
	}

	public function testValidateCheckRejectsContainsForSelectField(): void {
		$definition = $this->buildDefinition(7, 'contract_type', FieldType::SELECT->value);
		$definition->setOptions(json_encode(['CLT', 'PJ']));

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('contract_type')
			->willReturn($definition);

		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('The selected operator is not supported for this profile field');

		$this->check->validateCheck('contains', $this->encodeConfig('contract_type', 'CL'));
	}

	public function testExecuteCheckMatchesMultiselectWhenExpectedOptionIsPresent(): void {
		$definition = $this->buildDefinition(7, 'support_regions', FieldType::MULTISELECT->value);
		$definition->setOptions(json_encode(['LATAM', 'EMEA', 'APAC']));
		$value = $this->buildStoredValue(7, 'alice', '{"value":["LATAM","APAC"]}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('support_regions')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('is', $this->encodeConfig('support_regions', 'LATAM')));
	}

	public function testExecuteCheckMatchesMultiselectNegatedWhenExpectedOptionIsMissing(): void {
		$definition = $this->buildDefinition(7, 'support_regions', FieldType::MULTISELECT->value);
		$definition->setOptions(json_encode(['LATAM', 'EMEA', 'APAC']));
		$value = $this->buildStoredValue(7, 'alice', '{"value":["LATAM","APAC"]}');

		$this->fieldDefinitionService->expects($this->once())
			->method('findByFieldKey')
			->with('support_regions')
			->willReturn($definition);
		$this->fieldValueMapper->expects($this->once())
			->method('findByFieldDefinitionIdAndUserUid')
			->with(7, 'alice')
			->willReturn($value);

		$this->userSession->method('getUser')->willReturn($this->buildUser('alice'));

		$this->assertTrue($this->check->executeCheck('!is', $this->encodeConfig('support_regions', 'EMEA')));
	}

	private function buildDefinition(int $id, string $fieldKey, string $type): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId($id);
		$definition->setFieldKey($fieldKey);
		$definition->setLabel(ucfirst($fieldKey));
		$definition->setType($type);
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);
		$definition->setSortOrder(1);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime('2026-03-10T10:00:00+00:00'));
		$definition->setUpdatedAt(new \DateTime('2026-03-10T10:00:00+00:00'));

		return $definition;
	}

	private function buildStoredValue(int $fieldDefinitionId, string $userUid, string $valueJson): FieldValue {
		$value = new FieldValue();
		$value->setId(42);
		$value->setFieldDefinitionId($fieldDefinitionId);
		$value->setUserUid($userUid);
		$value->setValueJson($valueJson);
		$value->setCurrentVisibility('users');
		$value->setUpdatedByUid('admin');
		$value->setUpdatedAt(new \DateTime('2026-03-10T10:00:00+00:00'));

		return $value;
	}

	private function buildUser(string $uid): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);

		return $user;
	}

	private function encodeConfig(string $fieldKey, string|int|float|bool|null $value): string {
		try {
			return json_encode([
				'field_key' => $fieldKey,
				'value' => $value,
			], JSON_THROW_ON_ERROR);
		} catch (\JsonException $exception) {
			throw new InvalidArgumentException('Failed to encode test configuration', 0, $exception);
		}
	}
}
