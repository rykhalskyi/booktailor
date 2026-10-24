<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class ConflictException extends ApiException {
	public function __construct(string $message = 'Conflict') {
		parent::__construct($message, 409);
	}
}
