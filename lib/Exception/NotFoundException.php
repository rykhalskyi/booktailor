<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class NotFoundException extends ApiException {
	public function __construct(string $message = 'Not found') {
		parent::__construct($message, 404);
	}
}
