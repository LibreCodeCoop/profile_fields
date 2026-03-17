<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\AppInfo;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Listener\LoadWorkflowSettingsScriptsListener;
use OCA\ProfileFields\Listener\RegisterWorkflowCheckListener;
use OCA\ProfileFields\Listener\RegisterWorkflowEntityListener;
use OCA\ProfileFields\Listener\RegisterWorkflowOperationListener;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IRequest;
use OCP\User\Events\UserDeletedEvent;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;
use OCP\WorkflowEngine\Events\RegisterEntitiesEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
	public function testRegisterAddsWorkflowListeners(): void {
		$registrations = [];

		$registrationContext = $this->createMock(IRegistrationContext::class);
		$registrationContext->expects($this->exactly(6))
			->method('registerEventListener')
			->willReturnCallback(static function (string $event, string $listener, int $priority = 0) use (&$registrations): void {
				$registrations[] = [$event, $listener, $priority];
			});

		$application = new Application();
		$application->register($registrationContext);

		self::assertContains(['\\OCA\\Settings\\Events\\BeforeTemplateRenderedEvent', 'OCA\\ProfileFields\\Listener\\BeforeTemplateRenderedListener', 0], $registrations);
		self::assertContains([UserDeletedEvent::class, 'OCA\\ProfileFields\\Listener\\UserDeletedCleanupListener', 0], $registrations);
		self::assertContains([RegisterEntitiesEvent::class, RegisterWorkflowEntityListener::class, 0], $registrations);
		self::assertContains([RegisterOperationsEvent::class, RegisterWorkflowOperationListener::class, 0], $registrations);
		self::assertContains([RegisterChecksEvent::class, RegisterWorkflowCheckListener::class, 0], $registrations);
		self::assertContains([LoadSettingsScriptsEvent::class, LoadWorkflowSettingsScriptsListener::class, 0], $registrations);
	}

	public function testBootIgnoresUnsupportedRequestContext(): void {
		$request = $this->createMock(IRequest::class);
		$request->expects($this->once())
			->method('getPathInfo')
			->willThrowException(new \RuntimeException('unsupported path context'));
		$request->expects($this->once())
			->method('getRequestUri')
			->willThrowException(new \RuntimeException('unsupported uri context'));

		$bootContext = $this->createMock(IBootContext::class);
		$bootContext->expects($this->once())
			->method('injectFn')
			->willReturnCallback(static function (callable $fn) use ($request): mixed {
				return $fn($request);
			});

		$application = new Application();

		$application->boot($bootContext);

		self::assertTrue(true);
	}

	public function testBootIgnoresUnresolvableRequestInjection(): void {
		$bootContext = $this->createMock(IBootContext::class);
		$bootContext->expects($this->once())
			->method('injectFn')
			->willThrowException(new \RuntimeException('request unavailable'));

		$application = new Application();

		$application->boot($bootContext);

		self::assertTrue(true);
	}
}
