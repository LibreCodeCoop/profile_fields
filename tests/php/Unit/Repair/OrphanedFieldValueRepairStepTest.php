<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Repair;

use OCA\ProfileFields\Repair\OrphanedFieldValueRepairStep;
use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

class OrphanedFieldValueRepairStepTest extends TestCase {
	public function testGetNameReturnsHumanReadableLabel(): void {
		$service = $this->createMock(OrphanedFieldValueCleanupService::class);
		$step = new OrphanedFieldValueRepairStep($service);

		$this->assertSame('Repair orphaned profile field values', $step->getName());
	}

	public function testRunReportsRepairSummary(): void {
		$service = $this->createMock(OrphanedFieldValueCleanupService::class);
		$output = $this->createMock(IOutput::class);

		$service->expects($this->once())
			->method('repairOrphanedValues')
			->willReturn([
				'checked_user_uids' => 4,
				'orphaned_user_uids' => 2,
				'deleted_values' => 3,
			]);

		$output->expects($this->once())
			->method('info')
			->with('Profile fields: removed 3 orphaned values across 2 missing users after checking 4 user IDs.');

		$step = new OrphanedFieldValueRepairStep($service);
		$step->run($output);
	}
}
