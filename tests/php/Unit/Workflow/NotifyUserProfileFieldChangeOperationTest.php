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
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\NotifyUserProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotifyUserProfileFieldChangeOperationTest extends TestCase {
	private IManager&MockObject $notificationManager;
	private NotifyUserProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(IManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/comment.svg')
			->willReturn('/core/img/actions/comment.svg');
		$urlGenerator->method('getAbsoluteURL')
			->with('/core/img/actions/comment.svg')
			->willReturn('https://localhost/core/img/actions/comment.svg');

		$this->operation = new NotifyUserProfileFieldChangeOperation($this->notificationManager, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsCommentIcon(): void {
		$this->assertSame('/core/img/actions/comment.svg', $this->operation->getIcon());
	}

	public function testOnEventCreatesNotificationForAffectedUser(): void {
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

		$event = new ProfileFieldValueUpdatedEvent(new ProfileFieldValueWorkflowSubject(
			userUid: 'alice',
			actorUid: 'admin',
			fieldDefinition: $definition,
			currentValue: 'engineering',
			previousValue: 'finance',
			currentVisibility: 'users',
			previousVisibility: 'users',
		));

		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->once())
			->method('getFlows')
			->with(false)
			->willReturn([
				['id' => 11, 'name' => 'notify-user'],
			]);

		$notification = $this->createMock(INotification::class);
		$notification->expects($this->once())->method('setApp')->with(Application::APP_ID)->willReturnSelf();
		$notification->expects($this->once())->method('setUser')->with('alice')->willReturnSelf();
		$notification->expects($this->once())->method('setObject')->with('profile-field-change', '11:alice:department')->willReturnSelf();
		$notification->expects($this->once())->method('setSubject')
			->with('profile-field-change-notification', [
				'fieldLabel' => 'Department',
				'actorUid' => 'admin',
			])
			->willReturnSelf();
		$notification->expects($this->once())->method('setDateTime')->with($this->isInstanceOf(\DateTime::class))->willReturnSelf();
		$notification->expects($this->once())->method('setIcon')->with('https://localhost/core/img/actions/comment.svg')->willReturnSelf();

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventIgnoresUnsupportedEvents(): void {
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->never())->method('getFlows');
		$this->notificationManager->expects($this->never())->method('createNotification');

		$this->operation->onEvent('unsupported', new Event(), $ruleMatcher);
	}
}
