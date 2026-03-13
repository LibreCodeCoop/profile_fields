<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Enum;

enum FieldType: string {
	case TEXT = 'text';
	case NUMBER = 'number';

	/**
	 * @return list<string>
	 */
	public static function values(): array {
		return [
			self::TEXT->value,
			self::NUMBER->value,
		];
	}

	public static function isValid(string $value): bool {
		return self::tryFrom($value) !== null;
	}
}
