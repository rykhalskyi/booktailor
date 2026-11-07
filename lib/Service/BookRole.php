<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

final class BookRole {
	public const OWNER = 'owner';
	public const WRITE = 'write';
	public const READ = 'read';

	public static function isValidShareRole(string $role): bool {
		return $role === self::READ || $role === self::WRITE;
	}

	public static function canWrite(string $role): bool {
		return $role === self::OWNER || $role === self::WRITE;
	}

	public static function normalizeShareRole(string $role): ?string {
		return self::isValidShareRole($role) ? $role : null;
	}
}
