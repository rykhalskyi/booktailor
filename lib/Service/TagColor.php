<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

final class TagColor {
	public const DEFAULT = '#2563eb';

	/** @var list<string> */
	public const PALETTE = [
		'#2563eb',
		'#15803d',
		'#0f766e',
		'#c2410c',
		'#b91c1c',
		'#7e22ce',
		'#be185d',
		'#b45309',
		'#0e7490',
		'#475569',
	];

	public static function normalize(?string $color): ?string {
		if ($color === null) {
			return null;
		}

		return mb_strtolower(trim($color));
	}

	public static function isValid(string $color): bool {
		return in_array($color, self::PALETTE, true);
	}
}
