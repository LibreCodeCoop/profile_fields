<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\BackgroundJob;

use OCA\ProfileFields\BackgroundJob\UserDeletedCleanupJob;
use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

class TestUserDeletedCleanupJob extends UserDeletedCleanupJob {
	public function runPublic(mixed $argument): void {
		$this->run($argument);
	}
}

class UserDeletedCleanupJobTest extends TestCase {
	public function testRunDeletesValuesForDeletedUserUid(): void {
		$timeFactory = $this->createMock(ITimeFactory::class);
		$cleanupService = $this->createMock(OrphanedFieldValueCleanupService::class);

		$cleanupService->expects($this->once())
			->method('deleteValuesForDeletedUser')
			->with('alice')
			->willReturn(2);

		$job = new TestUserDeletedCleanupJob($timeFactory, $cleanupService);

		$job->runPublic(['userUid' => 'alice']);
	}
}
