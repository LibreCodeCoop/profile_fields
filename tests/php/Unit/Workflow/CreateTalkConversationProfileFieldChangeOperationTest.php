<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Workflow;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Workflow\CreateTalkConversationProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Talk\IBroker;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateTalkConversationProfileFieldChangeOperationTest extends TestCase {
	private IBroker&MockObject $broker;
	private IGroupManager&MockObject $groupManager;
	private IUserManager&MockObject $userManager;
	private CreateTalkConversationProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->broker = $this->createMock(IBroker::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			return $parameters === [] ? $text : vsprintf($text, $parameters);
		});
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/comment.svg')
			->willReturn('/core/img/actions/comment.svg');

		$this->operation = new CreateTalkConversationProfileFieldChangeOperation($this->broker, $this->groupManager, $this->userManager, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsCommentIcon(): void {
		$this->assertSame('/core/img/actions/comment.svg', $this->operation->getIcon());
	}

	public function testOnEventCreatesConversationWhenTalkBackendIsAvailable(): void {
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
		$ruleMatcher->expects($this->once())->method('getFlows')->with(false)->willReturn([
			['id' => 41, 'name' => 'talk'],
		]);

		$alice = $this->createMock(IUser::class);
		$alice->method('getUID')->willReturn('alice');
		$admin = $this->createMock(IUser::class);
		$admin->method('getUID')->willReturn('admin');
		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn([$admin]);

		$this->userManager->method('get')->willReturnMap([
			['alice', $alice],
		]);
		$this->groupManager->method('get')->with('admin')->willReturn($group);
		$this->broker->expects($this->once())->method('hasBackend')->willReturn(true);
		$this->broker->expects($this->once())
			->method('createConversation')
			->with(
				'Profile field changed: Department for user alice',
				$this->callback(function (array $moderators): bool {
					$this->assertCount(2, $moderators);
					return true;
				}),
				null,
			);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
		$this->addToAssertionCount(1);
	}

	public function testOnEventSkipsWhenTalkBackendIsUnavailable(): void {
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
		$ruleMatcher->expects($this->once())->method('getFlows')->with(false)->willReturn([
			['id' => 41, 'name' => 'talk'],
		]);

		$this->broker->expects($this->once())->method('hasBackend')->willReturn(false);
		$this->broker->expects($this->never())->method('createConversation');

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	private function createFieldDefinition(): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId(7);
		$definition->setFieldKey('department');
		$definition->setLabel('Department');
		$definition->setType(FieldType::TEXT->value);
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::USERS->value);
		$definition->setSortOrder(1);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());
		return $definition;
	}
}
