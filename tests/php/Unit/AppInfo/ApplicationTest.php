<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\AppInfo;

use OCA\ProfileFields\AppInfo\Application;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase {
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
