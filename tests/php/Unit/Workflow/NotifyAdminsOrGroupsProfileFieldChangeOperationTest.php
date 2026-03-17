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
use OCA\ProfileFields\Workflow\NotifyAdminsOrGroupsProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotifyAdminsOrGroupsProfileFieldChangeOperationTest extends TestCase {
	private IManager&MockObject $notificationManager;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private IL10N&MockObject $l10n;
	private NotifyAdminsOrGroupsProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->l10n = $this->createMock(IL10N::class);
		$this->l10n->method('t')->willReturnCallback(static function (string $text, array|string $parameters = []): string {
			if (!is_array($parameters) || $parameters === []) {
				return $text;
			}

			return str_replace(
				['%1$s', '%2$s', '%3$s'],
				array_map(static fn (mixed $parameter): string => (string)$parameter, $parameters),
				$text,
			);
		});
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/user-admin.svg')
			->willReturn('/core/img/actions/user-admin.svg');
		$urlGenerator->method('getAbsoluteURL')
			->with('/core/img/actions/user-admin.svg')
			->willReturn('https://localhost/core/img/actions/user-admin.svg');

		$this->operation = new NotifyAdminsOrGroupsProfileFieldChangeOperation($this->notificationManager, $this->groupManager, $this->userManager, $this->l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsAdminIcon(): void {
		$this->assertSame('/core/img/actions/user-admin.svg', $this->operation->getIcon());
	}

	public function testValidateOperationRejectsInvalidTargets(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('A valid target list is required');

		$this->operation->validateOperation('notify-admins-or-groups', [], '{"targets":"invalid"}');
	}

	public function testOnEventNotifiesResolvedTargets(): void {
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
				['id' => 31, 'name' => 'notify-targets', 'operation' => '{"targets":"user:bob,group:staff"}'],
			]);

		$bob = $this->createMock(IUser::class);
		$bob->method('getUID')->willReturn('bob');
		$staffUser = $this->createMock(IUser::class);
		$staffUser->method('getUID')->willReturn('carol');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$staffUser]);

		$this->userManager->method('get')->willReturnMap([
			['bob', $bob],
		]);
		$this->groupManager->method('get')->willReturnMap([
			['staff', $group],
		]);

		$notifications = [];
		$this->notificationManager->expects($this->exactly(2))
			->method('createNotification')
			->willReturnCallback(function () use (&$notifications) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->with(Application::APP_ID)->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setSubject')->with('profile_field_updated')->willReturnSelf();
				$notification->method('setMessage')->with('profile_field_updated_message', ['admin', 'alice', 'Department'])->willReturnSelf();
				$notification->method('setParsedSubject')->with('Profile field updated')->willReturnSelf();
				$notification->method('setParsedMessage')->with('admin changed alice\'s Department profile field.')->willReturnSelf();
				$notification->method('setIcon')->willReturnSelf();
				$notification->expects($this->once())->method('setUser')->with($this->logicalOr('bob', 'carol'))->willReturnSelf();
				$notifications[] = $notification;
				return $notification;
			});
		$this->notificationManager->expects($this->exactly(2))->method('notify');

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
		$this->addToAssertionCount(1);
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
