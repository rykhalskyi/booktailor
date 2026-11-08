<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class ForbiddenException extends ApiException {
	public function __construct(string $message = 'Forbidden') {
		parent::__construct($message, 403);
	}
}
