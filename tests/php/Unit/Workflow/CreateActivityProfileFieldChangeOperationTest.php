<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Workflow;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Workflow\CreateActivityProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\Activity\IEvent as IActivityEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateActivityProfileFieldChangeOperationTest extends TestCase {
	private IActivityManager&MockObject $activityManager;
	private CreateActivityProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->activityManager = $this->createMock(IActivityManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/recent.svg')
			->willReturn('/core/img/actions/recent.svg');
		$urlGenerator->method('getAbsoluteURL')
			->with('/core/img/actions/recent.svg')
			->willReturn('https://localhost/core/img/actions/recent.svg');

		$this->operation = new CreateActivityProfileFieldChangeOperation($this->activityManager, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsRecentIcon(): void {
		$this->assertSame('/core/img/actions/recent.svg', $this->operation->getIcon());
	}

	public function testValidateOperationRejectsCustomConfiguration(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('This workflow operation does not accept custom configuration');

		$this->operation->validateOperation('create-activity', [], 'custom');
	}

	public function testOnEventPublishesActivity(): void {
		$definition = $this->createFieldDefinition();
		$event = new ProfileFieldValueUpdatedEvent(new ProfileFieldValueWorkflowSubject(
			userUid: 'alice',
			actorUid: 'admin',
			fieldDefinition: $definition,
			currentValue: 'engineering',
			previousValue: 'finance',
			currentVisibility: 'users',
			previousVisibility: 'private',
		));

		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->once())
			->method('getFlows')
			->with(false)
			->willReturn([
				['id' => 21, 'name' => 'activity-entry'],
			]);

		$activity = $this->createMock(IActivityEvent::class);
		$activity->method('setApp')->with(Application::APP_ID)->willReturnSelf();
		$activity->method('setType')->with('profile_fields')->willReturnSelf();
		$activity->method('setAffectedUser')->with('alice')->willReturnSelf();
		$activity->method('setAuthor')->with('admin')->willReturnSelf();
		$activity->method('setSubject')->with('profile-field-change-activity', $this->isType('array'))->willReturnSelf();
		$activity->method('setParsedSubject')->with('Profile field change recorded')->willReturnSelf();
		$activity->method('setParsedMessage')->with($this->stringContains('Department'))->willReturnSelf();
		$activity->method('setObject')->with('profile-field', '21:alice:department', 'Department')->willReturnSelf();
		$activity->method('setTimestamp')->with($this->isType('int'))->willReturnSelf();
		$activity->method('setIcon')->with('https://localhost/core/img/actions/recent.svg')->willReturnSelf();

		$this->activityManager->expects($this->once())->method('generateEvent')->willReturn($activity);
		$this->activityManager->expects($this->once())->method('publish')->with($activity);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventIgnoresUnsupportedEvents(): void {
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->never())->method('getFlows');
		$this->activityManager->expects($this->never())->method('generateEvent');

		$this->operation->onEvent('unsupported', new Event(), $ruleMatcher);
	}

	private function createFieldDefinition(): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId(7);
		$definition->setFieldKey('department');
		$definition->setLabel('Department');
		$definition->setType(FieldType::TEXT->value);
		$definition->setAdminOnly(false);
		$definition->setUserEditable(true);
		$definition->setUserVisible(true);
		$definition->setInitialVisibility('users');
		$definition->setSortOrder(1);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());
		return $definition;
	}
}
