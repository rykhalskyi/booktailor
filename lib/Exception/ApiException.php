<?php

declare(strict_types=1);

namespace OCA\BookTailor\Exception;

class ApiException extends \Exception {
	public function __construct(
		string $message,
		private readonly int $statusCode,
	) {
		parent::__construct($message);
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}
}
