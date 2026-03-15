<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrphanedFieldValueCleanupServiceTest extends TestCase {
	private FieldValueMapper&MockObject $fieldValueMapper;
	private IUserManager&MockObject $userManager;
	private OrphanedFieldValueCleanupService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->service = new OrphanedFieldValueCleanupService($this->fieldValueMapper, $this->userManager);
	}

	public function testDeleteValuesForDeletedUserDeletesOrphanedOwnerValues(): void {
		$this->userManager->expects($this->once())
			->method('userExists')
			->with('alice')
			->willReturn(false);

		$this->fieldValueMapper->expects($this->once())
			->method('deleteByUserUid')
			->with('alice')
			->willReturn(3);

		$this->assertSame(3, $this->service->deleteValuesForDeletedUser('alice'));
	}

	public function testDeleteValuesForDeletedUserKeepsExistingOwnerValues(): void {
		$this->userManager->expects($this->once())
			->method('userExists')
			->with('alice')
			->willReturn(true);

		$this->fieldValueMapper->expects($this->never())
			->method('deleteByUserUid');

		$this->assertSame(0, $this->service->deleteValuesForDeletedUser('alice'));
	}

	public function testRepairOrphanedValuesRemovesOnlyMissingUsers(): void {
		$this->fieldValueMapper->expects($this->once())
			->method('findDistinctUserUids')
			->willReturn(['alice', 'bob', 'carol']);

		$this->userManager->expects($this->exactly(3))
			->method('userExists')
			->willReturnMap([
				['alice', false],
				['bob', true],
				['carol', false],
			]);

		$this->fieldValueMapper->expects($this->exactly(2))
			->method('deleteByUserUid')
			->willReturnMap([
				['alice', 2],
				['carol', 1],
			]);

		$this->assertSame([
			'checked_user_uids' => 3,
			'orphaned_user_uids' => 2,
			'deleted_values' => 3,
		], $this->service->repairOrphanedValues());
	}
}
