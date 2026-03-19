<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Enum;

enum FieldEditPolicy: string {
	case ADMINS = 'admins';
	case USERS = 'users';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return [
			self::ADMINS->value,
			self::USERS->value,
		];
	}

	public static function isValid(string $value): bool {
		return self::tryFrom($value) !== null;
	}

	public function userCanEdit(): bool {
		return $this === self::USERS;
	}
}
