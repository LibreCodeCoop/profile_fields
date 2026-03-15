<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use OCA\ProfileFields\Db\FieldValueMapper;
use OCP\IUserManager;

class OrphanedFieldValueCleanupService {
	public function __construct(
		private FieldValueMapper $fieldValueMapper,
		private IUserManager $userManager,
	) {
	}

	public function deleteValuesForDeletedUser(string $userUid): int {
		if ($this->userManager->userExists($userUid)) {
			return 0;
		}

		return $this->fieldValueMapper->deleteByUserUid($userUid);
	}

	/**
	 * @return array{checked_user_uids: int, orphaned_user_uids: int, deleted_values: int}
	 */
	public function repairOrphanedValues(): array {
		$checkedUserUids = 0;
		$orphanedUserUids = 0;
		$deletedValues = 0;

		foreach ($this->fieldValueMapper->findDistinctUserUids() as $userUid) {
			$checkedUserUids++;

			if ($this->userManager->userExists($userUid)) {
				continue;
			}

			$orphanedUserUids++;
			$deletedValues += $this->fieldValueMapper->deleteByUserUid($userUid);
		}

		return [
			'checked_user_uids' => $checkedUserUids,
			'orphaned_user_uids' => $orphanedUserUids,
			'deleted_values' => $deletedValues,
		];
	}
}
