<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Db\BookShareMapper;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Exception\ForbiddenException;
use OCA\BookTailor\Exception\NotFoundException;

class BookAccessService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookMapper $bookMapper,
		private readonly BookShareMapper $bookShareMapper,
	) {
	}

	/**
	 * @return list<array{book: Book, role: string}>
	 */
	public function listAccessible(string $userId, ?int $limit = null, int $offset = 0): array {
		/** @var array<int, array{book: Book, role: string}> $entries */
		$entries = [];
		foreach ($this->bookMapper->findAllByUser($userId) as $book) {
			$entries[$book->getId()] = ['book' => $book, 'role' => BookRole::OWNER];
		}

		foreach ($this->bookShareMapper->findBySharedWith($userId) as $share) {
			$bookId = $share->getBookId();
			if (isset($entries[$bookId])) {
				continue;
			}

			$book = $this->bookMapper->findById($bookId);
			if ($book === null) {
				continue;
			}

			$role = BookRole::normalizeShareRole($share->getPermission()) ?? BookRole::READ;
			$entries[$bookId] = ['book' => $book, 'role' => $role];
		}

		$result = array_values($entries);
		usort($result, static function (array $a, array $b): int {
			/** @var Book $bookA */
			$bookA = $a['book'];
			/** @var Book $bookB */
			$bookB = $b['book'];

			return strcasecmp($bookA->getName(), $bookB->getName())
				?: ($bookA->getId() <=> $bookB->getId());
		});

		if ($offset > 0) {
			$result = array_slice($result, $offset);
		}
		if ($limit !== null) {
			$result = array_slice($result, 0, $limit);
		}

		return $result;
	}

	public function getForRead(string $userId, int $bookId): Book {
		$book = $this->bookMapper->findById($bookId);
		if ($book === null || $this->roleFor($userId, $book) === null) {
			throw new NotFoundException('Book not found');
		}

		return $book;
	}

	public function getForWrite(string $userId, int $bookId): Book {
		$book = $this->bookMapper->findById($bookId);
		if ($book === null) {
			throw new NotFoundException('Book not found');
		}

		$role = $this->roleFor($userId, $book);
		if ($role === null) {
			throw new NotFoundException('Book not found');
		}
		if (!BookRole::canWrite($role)) {
			throw new ForbiddenException('You do not have permission to modify this book');
		}

		return $book;
	}

	public function getOwned(string $userId, int $bookId): Book {
		$book = $this->bookMapper->findById($bookId);
		if ($book === null || $book->getUserId() !== $userId) {
			throw new NotFoundException('Book not found');
		}

		return $book;
	}

	public function roleFor(string $userId, Book $book): ?string {
		if ($book->getUserId() === $userId) {
			return BookRole::OWNER;
		}

		$share = $this->bookShareMapper->findByBookAndUser($book->getId(), $userId);
		if ($share === null) {
			return null;
		}

		return BookRole::normalizeShareRole($share->getPermission()) ?? BookRole::READ;
	}
}
