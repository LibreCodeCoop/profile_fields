<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Behat\Hook\AfterScenario;
use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\RequestInfo;
use Libresign\NextcloudBehat\NextcloudApiContext;

class MockWebServerContext extends NextcloudApiContext {
	/** @var array<string, MockWebServer> */
	private array $mockServers = [];

	/**
	 * @Given /^the mock web server "([^"]*)" is started$/
	 */
	public function theMockWebServerIsStarted(string $serverName): void {
		if (isset($this->mockServers[$serverName]) && $this->mockServers[$serverName]->isRunning()) {
			return;
		}

		$server = new MockWebServer();
		$server->start();
		$this->mockServers[$serverName] = $server;
	}

	/**
	 * @Given /^save the mock web server "([^"]*)" root URL as "([^"]*)"$/
	 */
	public function saveTheMockWebServerRootUrlAs(string $serverName, string $fieldName): void {
		$this->fields[$fieldName] = $this->getMockServer($serverName)->getServerRoot();
	}

	/**
	 * @When /^read the last request from mock web server "([^"]*)"$/
	 */
	public function readTheLastRequestFromMockWebServer(string $serverName): void {
		$request = $this->getLastRequest($serverName);
		self::$commandOutput = json_encode($request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}

	/**
	 * @When /^read the last request body from mock web server "([^"]*)"$/
	 */
	public function readTheLastRequestBodyFromMockWebServer(string $serverName): void {
		$input = $this->getLastRequest($serverName)->getInput();

		try {
			$decoded = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
			self::$commandOutput = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			self::$commandOutput = $input;
		}
	}

	/**
	 * @When /^read the last Nextcloud log entry containing "([^"]*)"$/
	 */
	public function readTheLastNextcloudLogEntryContaining(string $needle): void {
		$logPath = static::findParentDirContainingFile('console.php') . '/data/nextcloud.log';

		$deadline = microtime(true) + 4;
		do {
			$entry = $this->findLastLogEntryContaining($logPath, $needle);
			if ($entry !== null) {
				self::$commandOutput = $entry;
				return;
			}

			usleep(200000);
		} while (microtime(true) < $deadline);

		throw new RuntimeException('Nextcloud log does not contain: ' . $needle);
	}

	#[AfterScenario()]
	public function stopMockWebServers(): void {
		foreach ($this->mockServers as $server) {
			if ($server->isRunning()) {
				$server->stop();
			}
		}

		$this->mockServers = [];
	}

	protected function beforeRequest(string $fullUrl, array $options): array {
		[$fullUrl, $options] = parent::beforeRequest($fullUrl, $options);

		if (isset($options['body']) && is_string($options['body'])) {
			$options['body'] = $this->parseText($options['body']);
		}

		return [$fullUrl, $options];
	}

	private function getMockServer(string $serverName): MockWebServer {
		if (!isset($this->mockServers[$serverName])) {
			throw new RuntimeException('Mock web server "' . $serverName . '" is not started');
		}

		return $this->mockServers[$serverName];
	}

	private function getLastRequest(string $serverName): RequestInfo {
		$request = $this->getMockServer($serverName)->getLastRequest();
		if (!$request instanceof RequestInfo) {
			throw new RuntimeException('Mock web server "' . $serverName . '" has not received any request yet');
		}

		return $request;
	}

	private function findLastLogEntryContaining(string $logPath, string $needle): ?string {
		if (!is_file($logPath)) {
			throw new RuntimeException('Nextcloud log file not found at ' . $logPath);
		}

		$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if (!is_array($lines)) {
			throw new RuntimeException('Unable to read Nextcloud log file at ' . $logPath);
		}

		for ($index = count($lines) - 1; $index >= 0; $index--) {
			if (str_contains($lines[$index], $needle)) {
				return $lines[$index];
			}
		}

		return null;
	}
}
