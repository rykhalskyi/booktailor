<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\IDBConnection;

class BookService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookMapper $bookMapper,
		private readonly StructureService $structureService,
		private readonly StorageService $storage,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return list<Book>
	 */
	public function list(string $userId, ?int $limit = null, int $offset = 0): array {
		return $this->bookMapper->findAllByUser($userId, $limit, $offset);
	}

	public function get(string $userId, int $bookId): Book {
		$book = $this->bookMapper->findByIdAndUser($bookId, $userId);
		if ($book === null) {
			throw new NotFoundException('Book not found');
		}

		return $book;
	}

	public function create(string $userId, string $name, ?string $description = null): Book {
		$name = $this->validateName($name);
		$folder = $this->storage->createBookFolder($userId, $name);

		$this->db->beginTransaction();
		try {
			$now = new DateTime();
			$book = new Book();
			$book->setUserId($userId);
			$book->setName($name);
			$book->setDescription($this->normalizeDescription($description));
			$book->setRootFileId($folder->getId());
			$book->setCreatedAt($now);
			$book->setUpdatedAt($now);
			$book = $this->bookMapper->insert($book);

			$this->structureService->createDefaultStructure($book->getId());
			$this->db->commit();

			return $book;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			$this->storage->deleteNodeIfExists($folder);
			throw $e;
		}
	}

	public function update(string $userId, int $bookId, string $name, ?string $description = null): Book {
		$book = $this->get($userId, $bookId);
		$name = $this->validateName($name);
		$oldName = $book->getName();

		$folder = $this->storage->getBookFolder($book);
		$this->storage->renameNode($folder, $name);

		try {
			$book->setName($name);
			if ($description !== null) {
				$book->setDescription($this->normalizeDescription($description));
			}
			$book->setUpdatedAt(new DateTime());

			return $this->bookMapper->update($book);
		} catch (\Throwable $e) {
			try {
				$this->storage->renameNode($folder, $oldName);
			} catch (\Throwable) {
				// Best-effort rollback of the visible name.
			}
			throw $e;
		}
	}

	public function delete(string $userId, int $bookId): void {
		$book = $this->get($userId, $bookId);

		$fileId = $book->getRootFileId();
		if ($fileId !== null) {
			$folder = $this->storage->findNodeById($userId, $fileId, \OCP\Files\Folder::class);
			$this->storage->deleteNodeIfExists($folder);
		}

		$this->bookMapper->delete($book);
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Book name is required');
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Book name is too long');
		}

		return $name;
	}

	private function normalizeDescription(?string $description): ?string {
		if ($description === null) {
			return null;
		}
		$description = trim($description);

		return $description === '' ? null : $description;
	}
}
