<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\AppInfo;

use OCA\ProfileFields\Listener\BeforeTemplateRenderedListener;
use OCA\ProfileFields\Listener\LoadWorkflowSettingsScriptsListener;
use OCA\ProfileFields\Listener\RegisterWorkflowCheckListener;
use OCA\ProfileFields\Listener\RegisterWorkflowEntityListener;
use OCA\ProfileFields\Listener\RegisterWorkflowOperationListener;
use OCA\ProfileFields\Listener\UserDeletedCleanupListener;
use OCA\ProfileFields\Notification\ProfileFieldWorkflowNotifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IRequest;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;
use OCP\WorkflowEngine\Events\RegisterEntitiesEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

/**
 * @codeCoverageIgnore
 */
class Application extends App implements IBootstrap {
	public const APP_ID = 'profile_fields';
	private static bool $userManagementAssetsLoaded = false;

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(ProfileFieldWorkflowNotifier::class);
		$context->registerEventListener('\\OCA\\Settings\\Events\\BeforeTemplateRenderedEvent', BeforeTemplateRenderedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedCleanupListener::class);
		$context->registerEventListener(RegisterEntitiesEvent::class, RegisterWorkflowEntityListener::class);
		$context->registerEventListener(RegisterOperationsEvent::class, RegisterWorkflowOperationListener::class);
		$context->registerEventListener(RegisterChecksEvent::class, RegisterWorkflowCheckListener::class);
		$context->registerEventListener(LoadSettingsScriptsEvent::class, LoadWorkflowSettingsScriptsListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		try {
			$context->injectFn($this->bootWithRequest(...));
		} catch (\Throwable) {
			return;
		}
	}

	private function bootWithRequest(IRequest $request): void {
		$path = $this->readRequestString(static fn (): string|false => $request->getPathInfo());
		$requestUri = $this->readRequestString(static fn (): string => $request->getRequestUri());

		if (
			($path !== null && str_contains($path, '/settings/users'))
			|| ($requestUri !== null && str_contains($requestUri, '/settings/users'))
		) {
			self::loadUserManagementAssets();
		}
	}

	private function readRequestString(callable $reader): ?string {
		try {
			$value = $reader();
		} catch (\Throwable) {
			return null;
		}

		return is_string($value) && $value !== '' ? $value : null;
	}

	public static function loadUserManagementAssets(): void {
		if (self::$userManagementAssetsLoaded) {
			return;
		}

		Util::addStyle(self::APP_ID, 'profile_fields-user-management-action');
		Util::addScript(self::APP_ID, 'profile_fields-user-management-action');
		self::$userManagementAssetsLoaded = true;
	}
}
