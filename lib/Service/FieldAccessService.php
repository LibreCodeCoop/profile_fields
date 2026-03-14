<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Service;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldVisibility;

class FieldAccessService {
	public function canViewValue(?string $actorUid, string $ownerUid, string $currentVisibility, bool $actorIsAdmin): bool {
		if ($actorIsAdmin) {
			return true;
		}

		if ($actorUid !== null && $actorUid === $ownerUid) {
			return true;
		}

		return match (FieldVisibility::from($currentVisibility)) {
			FieldVisibility::PUBLIC => true,
			FieldVisibility::USERS => $actorUid !== null,
			FieldVisibility::PRIVATE => false,
		};
	}

	public function canEditValue(?string $actorUid, string $ownerUid, FieldDefinition $definition, bool $actorIsAdmin): bool {
		if ($actorIsAdmin) {
			return true;
		}

		if ($actorUid === null || $actorUid !== $ownerUid) {
			return false;
		}

		if (!$definition->getUserVisible()) {
			return false;
		}

		if ($definition->getAdminOnly()) {
			return false;
		}

		return $definition->getUserEditable();
	}

	public function canChangeVisibility(?string $actorUid, string $ownerUid, bool $actorIsAdmin): bool {
		if ($actorIsAdmin) {
			return true;
		}

		return $actorUid !== null && $actorUid === $ownerUid;
	}
}
