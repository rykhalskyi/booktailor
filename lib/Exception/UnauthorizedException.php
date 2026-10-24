<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class UnauthorizedException extends ApiException {
	public function __construct(string $message = 'Not logged in') {
		parent::__construct($message, 401);
	}
}
