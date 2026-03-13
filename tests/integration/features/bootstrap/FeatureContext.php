<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Libresign\NextcloudBehat\NextcloudApiContext;

class FeatureContext implements Context {
	#[BeforeSuite()]
	public static function beforeSuite(BeforeSuiteScope $scope): void {
		unset($scope);
		self::runOccCommand('app:enable --force profile_fields');
	}

	#[BeforeScenario()]
	public static function beforeScenario(): void {
		self::runOccCommand('profile_fields:developer:reset --all');
	}

	private static function runOccCommand(string $command): void {
		NextcloudApiContext::runBashCommandWithResultCode('php <nextcloudRootDir>/occ ' . $command, 0);
	}
}
