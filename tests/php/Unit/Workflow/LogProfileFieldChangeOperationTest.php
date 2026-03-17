<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Workflow;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\LogProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LogProfileFieldChangeOperationTest extends TestCase {
	private LoggerInterface&MockObject $logger;
	private LogProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(LoggerInterface::class);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/history.svg')
			->willReturn('/core/img/actions/history.svg');

		$this->operation = new LogProfileFieldChangeOperation($this->logger, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsHistoryIcon(): void {
		$this->assertSame('/core/img/actions/history.svg', $this->operation->getIcon());
	}

	public function testOnEventLogsEveryMatchingRule(): void {
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
				['id' => 11, 'name' => 'dept-change', 'operation' => ''],
			]);

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'Profile field workflow rule matched',
				$this->callback(function (array $context): bool {
					$this->assertSame(11, $context['rule_id']);
					$this->assertSame('dept-change', $context['rule_name']);
					$this->assertSame('department', $context['field_key']);
					$this->assertSame('alice', $context['user_uid']);
					$this->assertSame('admin', $context['actor_uid']);
					$this->assertSame('finance', $context['previous_value']);
					$this->assertSame('engineering', $context['current_value']);
					return true;
				}),
			);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventIgnoresUnsupportedEvents(): void {
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->never())->method('getFlows');
		$this->logger->expects($this->never())->method('warning');

		$this->operation->onEvent('unsupported', new Event(), $ruleMatcher);
	}
}
