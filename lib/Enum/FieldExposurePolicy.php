<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Enum;

enum FieldExposurePolicy: string {
	case HIDDEN = 'hidden';
	case PRIVATE = 'private';
	case USERS = 'users';
	case PUBLIC = 'public';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return [
			self::HIDDEN->value,
			self::PRIVATE->value,
			self::USERS->value,
			self::PUBLIC->value,
		];
	}

	public static function isValid(string $value): bool {
		return self::tryFrom($value) !== null;
	}

	public function isUserVisible(): bool {
		return $this !== self::HIDDEN;
	}

	public function initialVisibility(): FieldVisibility {
		return match ($this) {
			self::HIDDEN, self::PRIVATE => FieldVisibility::PRIVATE,
			self::USERS => FieldVisibility::USERS,
			self::PUBLIC => FieldVisibility::PUBLIC,
		};
	}
}
