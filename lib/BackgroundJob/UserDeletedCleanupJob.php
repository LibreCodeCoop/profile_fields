<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\BackgroundJob;

use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

class UserDeletedCleanupJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private OrphanedFieldValueCleanupService $cleanupService,
	) {
		parent::__construct($time);
	}

	#[\Override]
	protected function run($argument): void {
		if (!is_array($argument) || !isset($argument['userUid']) || !is_string($argument['userUid']) || $argument['userUid'] === '') {
			return;
		}

		$this->cleanupService->deleteValuesForDeletedUser($argument['userUid']);
	}
}
