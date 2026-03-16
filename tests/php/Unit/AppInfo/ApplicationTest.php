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
use OCP\IServerContainer;
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

		$serverContainer = $this->createMock(IServerContainer::class);
		$serverContainer->expects($this->once())
			->method('get')
			->with(IRequest::class)
			->willReturn($request);

		$bootContext = $this->createMock(IBootContext::class);
		$bootContext->expects($this->once())
			->method('getServerContainer')
			->willReturn($serverContainer);

		$application = new Application();

		$application->boot($bootContext);

		self::assertTrue(true);
	}
}
