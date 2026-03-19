<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Workflow;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Workflow\EmailUserProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EmailUserProfileFieldChangeOperationTest extends TestCase {
	private IMailer&MockObject $mailer;
	private IUserManager&MockObject $userManager;
	private EmailUserProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->mailer = $this->createMock(IMailer::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/mail.svg')
			->willReturn('/core/img/actions/mail.svg');

		$this->operation = new EmailUserProfileFieldChangeOperation($this->mailer, $this->userManager, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsMailIcon(): void {
		$this->assertSame('/core/img/actions/mail.svg', $this->operation->getIcon());
	}

	public function testValidateOperationRejectsCustomConfiguration(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('A valid email template configuration is required');

		$this->operation->validateOperation('email-user', [], 'custom');
	}

	public function testValidateOperationAcceptsTemplateConfiguration(): void {
		$this->operation->validateOperation('email-user', [], '{"subjectTemplate":"Profile update: {{fieldLabel}}","bodyTemplate":"Field {{fieldLabel}} changed from {{previousValue}} to {{currentValue}}."}');
		$this->assertTrue(true);
	}

	public function testOnEventSendsMailToAffectedUser(): void {
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
				[
					'id' => 17,
					'name' => 'email-user',
					'operation' => '{"subjectTemplate":"Update: {{fieldLabel}}","bodyTemplate":"Field {{fieldLabel}} changed from {{previousValue}} to {{currentValue}} by {{actorUid}}."}',
				],
			]);

		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getEMailAddress')->willReturn('alice@example.test');
		$user->expects($this->once())->method('getDisplayName')->willReturn('Alice');
		$this->userManager->expects($this->once())->method('get')->with('alice')->willReturn($user);

		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['alice@example.test' => 'Alice'])->willReturnSelf();
		$message->expects($this->once())->method('setSubject')->with('Update: Department')->willReturnSelf();
		$message->expects($this->once())
			->method('setPlainBody')
			->with($this->callback(function (string $body): bool {
				$this->assertSame('Field Department changed from finance to engineering by admin.', $body);
				$this->assertStringContainsString('admin', $body);
				return true;
			}))
			->willReturnSelf();

		$this->mailer->expects($this->once())->method('createMessage')->willReturn($message);
		$this->mailer->expects($this->once())->method('send')->with($message)->willReturn([]);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventSkipsMailWhenAffectedUserHasNoEmail(): void {
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
				['id' => 17, 'name' => 'email-user', 'operation' => ''],
			]);

		$user = $this->createMock(IUser::class);
		$user->expects($this->once())->method('getEMailAddress')->willReturn(null);
		$user->expects($this->never())->method('getDisplayName');
		$this->userManager->expects($this->once())->method('get')->with('alice')->willReturn($user);

		$this->mailer->expects($this->never())->method('createMessage');
		$this->mailer->expects($this->never())->method('send');

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventIgnoresUnsupportedEvents(): void {
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->never())->method('getFlows');
		$this->userManager->expects($this->never())->method('get');
		$this->mailer->expects($this->never())->method('createMessage');

		$this->operation->onEvent('unsupported', new Event(), $ruleMatcher);
	}
}
