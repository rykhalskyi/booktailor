<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Db\AssetTagMapper;
use OCA\BookTailor\Db\TagMapper;
use OCA\BookTailor\Entity\Tag;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\IDBConnection;

class TagService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly TagMapper $tagMapper,
		private readonly AssetTagMapper $assetTagMapper,
		private readonly AssetService $assetService,
		private readonly BookService $bookService,
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return list<Tag>
	 */
	public function listForBook(string $userId, int $bookId): array {
		$this->bookService->get($userId, $bookId);

		return $this->tagMapper->findByBook($bookId);
	}

	public function create(string $userId, int $bookId, string $name, ?string $color = null): Tag {
		$this->bookService->get($userId, $bookId);
		$name = $this->validateName($name);
		$slug = $this->slugify($name);
		$color = $this->validateColor($color) ?? TagColor::DEFAULT;

		if ($this->tagMapper->findByBookAndSlug($bookId, $slug) !== null) {
			throw new ConflictException('A tag with this name already exists');
		}

		$tag = new Tag();
		$tag->setBookId($bookId);
		$tag->setName($name);
		$tag->setSlug($slug);
		$tag->setColor($color);

		try {
			return $this->tagMapper->insert($tag);
		} catch (\OCP\DB\Exception $e) {
			if (in_array($e->getReason(), [
				\OCP\DB\Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				\OCP\DB\Exception::REASON_CONSTRAINT_VIOLATION,
			], true)) {
				throw new ConflictException('A tag with this name already exists');
			}
			throw $e;
		}
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function rename(string $userId, int $tagId, string $name, ?string $color = null): Tag {
		return $this->update($userId, $tagId, $name, $color);
	}

	public function update(string $userId, int $tagId, ?string $name = null, ?string $color = null): Tag {
		$tag = $this->getOwned($userId, $tagId);
		if ($name === null && $color === null) {
			throw new ValidationException('Tag name or color is required');
		}

		if ($name !== null) {
			$name = $this->validateName($name);
			$slug = $this->slugify($name);

			$duplicate = $this->tagMapper->findByBookAndSlug($tag->getBookId(), $slug);
			if ($duplicate !== null && $duplicate->getId() !== $tag->getId()) {
				throw new ConflictException('A tag with this name already exists');
			}

			$tag->setName($name);
			$tag->setSlug($slug);
		}

		$validatedColor = $this->validateColor($color);
		if ($validatedColor !== null) {
			$tag->setColor($validatedColor);
		}

		return $this->tagMapper->update($tag);
	}

	public function delete(string $userId, int $tagId): void {
		$tag = $this->getOwned($userId, $tagId);
		$this->tagMapper->delete($tag);
	}

	/**
	 * @return list<Tag>
	 */
	public function listForAsset(string $userId, int $assetId): array {
		$context = $this->assetService->context($userId, $assetId);
		$tagIds = $this->assetTagMapper->findTagIdsByAsset($assetId);

		return $this->tagMapper->findByIdsAndBook($tagIds, $context['chapter']->getBookId());
	}

	/**
	 * @param array<array-key, int|string> $tagIds
	 * @return list<Tag>
	 */
	public function replaceForAsset(string $userId, int $assetId, array $tagIds): array {
		$context = $this->assetService->context($userId, $assetId);
		$bookId = $context['chapter']->getBookId();

		$normalized = [];
		foreach ($tagIds as $tagId) {
			$tagId = (int)$tagId;
			if ($tagId > 0 && !in_array($tagId, $normalized, true)) {
				$normalized[] = $tagId;
			}
		}

		$tags = $this->tagMapper->findByIdsAndBook($normalized, $bookId);
		if (count($tags) !== count($normalized)) {
			throw new NotFoundException('Tag not found');
		}

		$this->db->beginTransaction();
		try {
			$this->assetTagMapper->replaceForAsset($assetId, $normalized);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $tags;
	}

	public function getOwned(string $userId, int $tagId): Tag {
		$tag = $this->tagMapper->findById($tagId);
		if ($tag === null) {
			throw new NotFoundException('Tag not found');
		}

		$this->bookService->get($userId, $tag->getBookId());

		return $tag;
	}

	private function validateName(string $name): string {
		$name = trim($name);
		if ($name === '') {
			throw new ValidationException('Tag name is required');
		}
		if (mb_strlen($name) > 64) {
			throw new ValidationException('Tag name is too long');
		}

		return $name;
	}

	private function validateColor(?string $color): ?string {
		$color = TagColor::normalize($color);
		if ($color === null || $color === '') {
			return null;
		}
		if (!TagColor::isValid($color)) {
			throw new ValidationException('Choose one of the available tag colors');
		}

		return $color;
	}

	private function slugify(string $name): string {
		$slug = mb_strtolower(trim($name));
		$slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
		$slug = trim($slug, '-');
		if ($slug === '') {
			$slug = 'tag';
		}

		return mb_substr($slug, 0, 64);
	}
}
