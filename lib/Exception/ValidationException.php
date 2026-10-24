<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class ValidationException extends ApiException {
	public function __construct(string $message = 'Validation failed') {
		parent::__construct($message, 422);
	}
}
