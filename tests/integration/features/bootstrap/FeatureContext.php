<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;

class FeatureContext extends NextcloudApiContext {
	#[BeforeSuite()]
	public static function beforeSuite(BeforeSuiteScope $scope): void {
		parent::beforeSuite($scope);
		unset($scope);
		self::runOccCommand('app:enable --force profile_fields');
	}

	#[BeforeScenario()]
	public static function beforeScenario(): void {
		parent::beforeScenario();
		self::runOccCommand('profile_fields:developer:reset --all');
	}

	private static function runOccCommand(string $command): void {
		NextcloudApiContext::runBashCommandWithResultCode('php <nextcloudRootDir>/occ ' . $command, 0);
	}
}
