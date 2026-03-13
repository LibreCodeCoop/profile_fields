<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Enum;

enum FieldVisibility: string {
	case PRIVATE = 'private';
	case USERS = 'users';
	case PUBLIC = 'public';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return [
			self::PRIVATE->value,
			self::USERS->value,
			self::PUBLIC->value,
		];
	}

	public static function isValid(string $value): bool {
		return self::tryFrom($value) !== null;
	}
}
