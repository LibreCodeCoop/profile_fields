<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Listener;

use OCA\ProfileFields\BackgroundJob\UserDeletedCleanupJob;
use OCA\ProfileFields\Listener\UserDeletedCleanupListener;
use OCP\BackgroundJob\IJobList;
use OCP\IUser;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;

class UserDeletedCleanupListenerTest extends TestCase {
	public function testHandleEnqueuesCleanupJobForDeletedUser(): void {
		$jobList = $this->createMock(IJobList::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$jobList->expects($this->once())
			->method('add')
			->with(UserDeletedCleanupJob::class, ['userUid' => 'alice']);

		$listener = new UserDeletedCleanupListener($jobList);
		$listener->handle(new UserDeletedEvent($user));
	}
}
