<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Repair;

use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class OrphanedFieldValueRepairStep implements IRepairStep {
	public function __construct(
		private OrphanedFieldValueCleanupService $cleanupService,
	) {
	}

	#[\Override]
	public function getName(): string {
		return 'Repair orphaned profile field values';
	}

	#[\Override]
	public function run(IOutput $output): void {
		$result = $this->cleanupService->repairOrphanedValues();

		$output->info(sprintf(
			'Profile fields: removed %d orphaned values across %d missing users after checking %d user IDs.',
			$result['deleted_values'],
			$result['orphaned_user_uids'],
			$result['checked_user_uids'],
		));
	}
}
