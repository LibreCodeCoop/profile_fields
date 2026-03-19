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
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCA\ProfileFields\Workflow\SendWebhookProfileFieldChangeOperation;
use OCP\EventDispatcher\Event;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SendWebhookProfileFieldChangeOperationTest extends TestCase {
	private IClientService&MockObject $clientService;
	private IClient&MockObject $client;
	private SendWebhookProfileFieldChangeOperation $operation;

	protected function setUp(): void {
		parent::setUp();

		$this->clientService = $this->createMock(IClientService::class);
		$this->client = $this->createMock(IClient::class);
		$this->clientService->method('newClient')->willReturn($this->client);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnArgument(0);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')
			->with('core', 'actions/share.svg')
			->willReturn('/core/img/actions/share.svg');

		$this->operation = new SendWebhookProfileFieldChangeOperation($this->clientService, $l10n, $urlGenerator, new ProfileFieldValueSubjectContext());
	}

	public function testGetIconReturnsWebhookIcon(): void {
		$this->assertSame('/core/img/actions/share.svg', $this->operation->getIcon());
	}

	public function testValidateOperationRejectsMissingWebhookUrl(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('A valid HTTP or HTTPS webhook URL is required');

		$this->operation->validateOperation('send-webhook', [], '');
	}

	public function testValidateOperationRejectsUnsupportedScheme(): void {
		$this->expectException(\UnexpectedValueException::class);
		$this->expectExceptionMessage('A valid HTTP or HTTPS webhook URL is required');

		$this->operation->validateOperation('send-webhook', [], 'ftp://example.test/hook');
	}

	public function testValidateOperationAcceptsJsonConfiguration(): void {
		$this->operation->validateOperation('send-webhook', [], '{"url":"https://example.test/hooks/profile-fields","secret":"shared-secret","timeout":10,"retries":2,"headers":{"X-Environment":"test"}}');
		$this->assertTrue(true);
	}

	public function testOnEventPostsStructuredWebhookPayload(): void {
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
					'id' => 12,
					'name' => 'send-webhook',
					'operation' => '{"url":"https://example.test/hooks/profile-fields","secret":"shared-secret","timeout":10,"retries":2,"headers":{"X-Environment":"test"}}',
				],
			]);

		$this->client->expects($this->once())
			->method('post')
			->with(
				'https://example.test/hooks/profile-fields',
				$this->callback(function (array $options): bool {
					$this->assertSame('application/json', $options['headers']['Content-Type'] ?? null);
					$this->assertSame('application/json', $options['headers']['Accept'] ?? null);
					$this->assertSame('test', $options['headers']['X-Environment'] ?? null);
					$this->assertSame(10, $options['timeout'] ?? null);
					$this->assertStringStartsWith('sha256=', $options['headers']['X-Profile-Fields-Signature'] ?? '');
					$this->assertNotSame('', $options['headers']['X-Profile-Fields-Timestamp'] ?? '');
					$this->assertIsString($options['body'] ?? null);

					$payload = json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR);
					$this->assertSame(12, $payload['rule']['id'] ?? null);
					$this->assertSame('send-webhook', $payload['rule']['name'] ?? null);
					$this->assertSame('department', $payload['field']['key'] ?? null);
					$this->assertSame('Department', $payload['field']['label'] ?? null);
					$this->assertSame('alice', $payload['user']['uid'] ?? null);
					$this->assertSame('admin', $payload['actor']['uid'] ?? null);
					$this->assertSame('finance', $payload['change']['previousValue'] ?? null);
					$this->assertSame('engineering', $payload['change']['currentValue'] ?? null);
					$this->assertSame('private', $payload['change']['previousVisibility'] ?? null);
					$this->assertSame('users', $payload['change']['currentVisibility'] ?? null);
					$this->assertSame(ProfileFieldValueUpdatedEvent::class, $payload['event']['name'] ?? null);
					return true;
				})
			);

		$this->operation->onEvent(ProfileFieldValueUpdatedEvent::class, $event, $ruleMatcher);
	}

	public function testOnEventIgnoresUnsupportedEvents(): void {
		$ruleMatcher = $this->createMock(IRuleMatcher::class);
		$ruleMatcher->expects($this->never())->method('getFlows');
		$this->client->expects($this->never())->method('post');

		$this->operation->onEvent('unsupported', new Event(), $ruleMatcher);
	}
}
