<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException as FileNotFoundException;

class StorageService {
	private const BOOKS_ROOT = 'BookTailor';
	private const MAX_NAME_LENGTH = 180;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IRootFolder $rootFolder,
	) {
	}

	public function getUserFolder(string $userId): Folder {
		$folder = $this->rootFolder->getUserFolder($userId);
		if (!$folder instanceof Folder) {
			throw new NotFoundException('User folder not found');
		}

		return $folder;
	}

	public function findNodeById(string $userId, int $fileId, ?string $expectedClass = null): ?Node {
		$nodes = $this->getUserFolder($userId)->getById($fileId);
		foreach ($nodes as $node) {
			if ($expectedClass === null || $node instanceof $expectedClass) {
				return $node;
			}
		}

		return null;
	}

	public function getNodeById(string $userId, int $fileId, string $expectedClass): Node {
		$node = $this->findNodeById($userId, $fileId, $expectedClass);
		if ($node === null) {
			throw new NotFoundException('File not found');
		}

		return $node;
	}

	public function getBookFolder(Book $book): Folder {
		$fileId = $book->getRootFileId();
		if ($fileId === null) {
			throw new NotFoundException('Book folder not found');
		}

		$folder = $this->getNodeById($book->getUserId(), $fileId, Folder::class);
		if (!$folder instanceof Folder) {
			throw new NotFoundException('Book folder not found');
		}

		return $folder;
	}

	public function getChapterFolderForUser(string $userId, Chapter $chapter): Folder {
		$fileId = $chapter->getFileId();
		if ($fileId === null) {
			throw new NotFoundException('Chapter folder not found');
		}

		$folder = $this->getNodeById($userId, $fileId, Folder::class);
		if (!$folder instanceof Folder) {
			throw new NotFoundException('Chapter folder not found');
		}

		return $folder;
	}

	public function getAssetFile(string $userId, Asset $asset): File {
		$fileId = $asset->getFileId();
		if ($fileId === null) {
			throw new NotFoundException('Asset file not found');
		}

		$file = $this->getNodeById($userId, $fileId, File::class);
		if (!$file instanceof File) {
			throw new NotFoundException('Asset file not found');
		}

		return $file;
	}

	public function createBookFolder(string $userId, string $displayName): Folder {
		$userFolder = $this->getUserFolder($userId);

		if ($userFolder->nodeExists(self::BOOKS_ROOT)) {
			$root = $userFolder->get(self::BOOKS_ROOT);
			if (!$root instanceof Folder) {
				throw new ConflictException('The BookTailor path is not a folder');
			}
		} else {
			$root = $userFolder->newFolder(self::BOOKS_ROOT);
		}

		$name = $this->uniqueChildName($root, $displayName);

		return $root->newFolder($name);
	}

	public function createChapterFolder(Folder $parent, string $displayName): Folder {
		$name = $this->uniqueChildName($parent, $displayName);

		return $parent->newFolder($name);
	}

	/**
	 * @return array{file: File, fileName: string}
	 */
	public function createAssetFile(Folder $chapterFolder, string $displayName, string $content = ''): array {
		$base = $this->stripMarkdownExtension($displayName);
		$fileName = $this->uniqueChildName($chapterFolder, $base, '.md');
		$file = $chapterFolder->newFile($fileName, $content);

		return ['file' => $file, 'fileName' => $fileName];
	}

	public function renameNode(Node $node, string $displayName, string $extension = ''): string {
		$parent = $node->getParent();
		$name = $this->uniqueChildName($parent, $displayName, $extension, $node);
		if ($node->getName() === $name) {
			return $name;
		}

		$node->move($parent->getPath() . '/' . $name);

		return $name;
	}

	/** @psalm-suppress PossiblyUnusedReturnValue */
	public function moveNode(Node $node, Folder $targetParent): string {
		$name = $this->uniqueChildName($targetParent, $node->getName());
		$node->move($targetParent->getPath() . '/' . $name);

		return $name;
	}

	public function deleteNodeIfExists(?Node $node): void {
		if ($node === null) {
			return;
		}

		try {
			$node->delete();
		} catch (FileNotFoundException) {
			// Already gone.
		}
	}

	public function relativePathForFileId(string $userId, ?int $fileId): ?string {
		if ($fileId === null) {
			return null;
		}

		$userFolder = $this->getUserFolder($userId);
		$node = $this->findNodeById($userId, $fileId);
		if ($node === null) {
			return null;
		}

		return $userFolder->getRelativePath($node->getPath());
	}

	/**
	 * @param Node|null $ignore Node that already occupies the desired name
	 */
	public function uniqueChildName(Folder $parent, string $desiredName, string $extension = '', ?Node $ignore = null): string {
		$base = $this->sanitizeName($desiredName);
		$extension = $this->normalizeExtension($extension);
		$candidate = $base . $extension;
		$suffix = 2;

		while ($parent->nodeExists($candidate)) {
			if ($ignore !== null) {
				try {
					$existing = $parent->get($candidate);
					if ($existing->getId() === $ignore->getId()) {
						return $candidate;
					}
				} catch (FileNotFoundException) {
					// Fall through and try the next suffix.
				}
			}

			if ($suffix > 1000) {
				throw new ConflictException('Could not allocate a unique file name');
			}
			$candidate = $base . ' (' . $suffix . ')' . $extension;
			$suffix++;
		}

		return $candidate;
	}

	public function sanitizeName(string $name): string {
		$name = trim($name);
		$name = preg_replace('/[\/\\\\\x00-\x1F\x7F]/u', '-', $name) ?? '';
		$name = preg_replace('/\s+/u', ' ', $name) ?? '';
		$name = trim($name, " .\t\n\r\0\x0B");
		if ($name === '' || $name === '.' || $name === '..') {
			$name = 'Untitled';
		}

		if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
			$name = mb_substr($name, 0, self::MAX_NAME_LENGTH);
			$name = rtrim($name, " .\t\n\r\0\x0B");
			if ($name === '') {
				$name = 'Untitled';
			}
		}

		return $name;
	}

	public function stripMarkdownExtension(string $name): string {
		$name = trim($name);
		if (preg_match('/\.md$/i', $name) === 1) {
			$name = substr($name, 0, -3);
		}

		return $name;
	}

	private function normalizeExtension(string $extension): string {
		if ($extension === '') {
			return '';
		}

		return str_starts_with($extension, '.') ? $extension : '.' . $extension;
	}
}
