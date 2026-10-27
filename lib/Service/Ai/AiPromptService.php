<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use DateTime;
use OCA\BookTailor\Db\AiPromptMapper;
use OCA\BookTailor\Entity\AiPrompt;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\BookService;

class AiPromptService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly AiPromptMapper $promptMapper,
		private readonly BookService $bookService,
	) {
	}

	/**
	 * @return list<AiPrompt>
	 */
	public function listForBook(string $userId, int $bookId): array {
		$this->bookService->get($userId, $bookId);

		return $this->promptMapper->findByBook($bookId);
	}

	public function create(string $userId, int $bookId, string $name, string $text): AiPrompt {
		$this->bookService->get($userId, $bookId);
		$now = new DateTime();
		$prompt = new AiPrompt();
		$prompt->setBookId($bookId);
		$prompt->setUserId($userId);
		$prompt->setName($this->validateName($name));
		$prompt->setText($this->validateText($text));
		$prompt->setCreatedAt($now);
		$prompt->setUpdatedAt($now);

		return $this->promptMapper->insert($prompt);
	}

	public function update(string $userId, int $promptId, ?string $name = null, ?string $text = null): AiPrompt {
		$prompt = $this->getOwned($userId, $promptId);
		if ($name === null && $text === null) {
			throw new ValidationException('Prompt name or text is required');
		}
		if ($name !== null) {
			$prompt->setName($this->validateName($name));
		}
		if ($text !== null) {
			$prompt->setText($this->validateText($text));
		}
		$prompt->setUpdatedAt(new DateTime());

		return $this->promptMapper->update($prompt);
	}

	public function delete(string $userId, int $promptId): void {
		$this->promptMapper->delete($this->getOwned($userId, $promptId));
	}

	public function getOwned(string $userId, int $promptId): AiPrompt {
		$prompt = $this->promptMapper->findById($promptId);
		if ($prompt === null) {
			throw new NotFoundException('AI prompt not found');
		}
		$this->bookService->get($userId, $prompt->getBookId());

		return $prompt;
	}

	/**
	 * @return array{id: int, bookId: int, name: string, text: string, source: string, createdAt: string, updatedAt: string}
	 */
	public function serialize(AiPrompt $prompt): array {
		return [
			'id' => $prompt->getId(),
			'bookId' => $prompt->getBookId(),
			'name' => $prompt->getName(),
			'text' => $prompt->getText(),
			'source' => 'user',
			'createdAt' => $prompt->getCreatedAt()->format(\DateTimeInterface::ATOM),
			'updatedAt' => $prompt->getUpdatedAt()->format(\DateTimeInterface::ATOM),
		];
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Prompt name is required');
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Prompt name is too long');
		}

		return $name;
	}

	private function validateText(string $text): string {
		$text = trim($text);
		if ($text === '') {
			throw new ValidationException('Prompt text is required');
		}
		if (mb_strlen($text) > 20000) {
			throw new ValidationException('Prompt text is too long');
		}

		return $text;
	}
}
