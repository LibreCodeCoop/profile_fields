<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\AppInfo;

use OCA\ProfileFields\Listener\BeforeTemplateRenderedListener;
use OCA\ProfileFields\Listener\UserDeletedCleanupListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IRequest;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;

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
		$context->registerEventListener('\\OCA\\Settings\\Events\\BeforeTemplateRenderedEvent', BeforeTemplateRenderedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedCleanupListener::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$request = $context->getServerContainer()->get(IRequest::class);
		$path = $request->getPathInfo();
		$requestUri = $request->getRequestUri();

		if (
			($path !== false && str_contains($path, '/settings/users'))
			|| str_contains($requestUri, '/settings/users')
		) {
			self::loadUserManagementAssets();
		}
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
