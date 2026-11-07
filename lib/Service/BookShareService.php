<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use DateTimeInterface;
use OCA\BookTailor\Db\BookShareMapper;
use OCA\BookTailor\Entity\BookShare;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class BookShareService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly BookShareMapper $shareMapper,
		private readonly BookAccessService $bookAccess,
		private readonly FileShareService $fileShareService,
		private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return list<array{id: int, bookId: int, userId: string, displayName: string, role: string, createdAt: string, updatedAt: string}>
	 */
	public function listForBook(string $ownerId, int $bookId): array {
		$this->bookAccess->getOwned($ownerId, $bookId);

		return array_map(
			fn (BookShare $share): array => $this->serialize($share),
			$this->shareMapper->findByBook($bookId),
		);
	}

	/**
	 * @return array{id: int, bookId: int, userId: string, displayName: string, role: string, createdAt: string, updatedAt: string}
	 */
	public function create(string $ownerId, int $bookId, string $targetUid, string $role): array {
		$book = $this->bookAccess->getOwned($ownerId, $bookId);
		$role = $this->validateRole($role);
		$targetUid = trim($targetUid);
		if ($targetUid === '') {
			throw new ValidationException('A user is required');
		}
		if ($targetUid === $ownerId) {
			throw new ValidationException('A book cannot be shared with its owner');
		}

		$user = $this->userManager->get($targetUid);
		if ($user === null || !$user->isEnabled()) {
			throw new ValidationException('The selected user is not available');
		}

		if ($this->shareMapper->findByBookAndUser($bookId, $targetUid) !== null) {
			throw new ConflictException('The book is already shared with this user');
		}

		$native = $this->fileShareService->createUserShare($book, $targetUid, $role);

		$now = new DateTime();
		$share = new BookShare();
		$share->setBookId($bookId);
		$share->setSharedWith($targetUid);
		$share->setPermission($role);
		$share->setNextcloudShareId($native['share']->getFullId());
		$share->setCreatedAt($now);
		$share->setUpdatedAt($now);

		try {
			$share = $this->shareMapper->insert($share);
		} catch (\Throwable $e) {
			if ($native['created']) {
				try {
					$this->fileShareService->deleteUserShare($ownerId, $native['share']->getFullId());
				} catch (\Throwable $cleanupError) {
					$this->logger->warning('Could not roll back a native book share', [
						'bookId' => $bookId,
						'targetUid' => $targetUid,
						'exception' => $cleanupError,
					]);
				}
			}
			throw $e;
		}

		return $this->serialize($share);
	}

	/**
	 * @return array{id: int, bookId: int, userId: string, displayName: string, role: string, createdAt: string, updatedAt: string}
	 */
	public function update(string $ownerId, int $bookId, int $shareId, string $role): array {
		$book = $this->bookAccess->getOwned($ownerId, $bookId);
		$role = $this->validateRole($role);
		$share = $this->getShareForOwner($ownerId, $bookId, $shareId);

		if ($share->getPermission() === $role) {
			return $this->serialize($share);
		}

		$nativeShareId = $share->getNextcloudShareId();
		if ($nativeShareId === null) {
			$native = $this->fileShareService->createUserShare($book, $share->getSharedWith(), $role);
			$share->setNextcloudShareId($native['share']->getFullId());
		} else {
			try {
				$this->fileShareService->updateUserShare($ownerId, $nativeShareId, $role);
			} catch (NotFoundException) {
				$native = $this->fileShareService->createUserShare($book, $share->getSharedWith(), $role);
				$share->setNextcloudShareId($native['share']->getFullId());
			}
		}

		$share->setPermission($role);
		$share->setUpdatedAt(new DateTime());

		return $this->serialize($this->shareMapper->update($share));
	}

	public function delete(string $ownerId, int $bookId, int $shareId): void {
		$this->bookAccess->getOwned($ownerId, $bookId);
		$share = $this->getShareForOwner($ownerId, $bookId, $shareId);

		$nativeShareId = $share->getNextcloudShareId();
		if ($nativeShareId !== null) {
			$this->fileShareService->deleteUserShare($ownerId, $nativeShareId);
		}

		$this->shareMapper->delete($share);
	}

	/**
	 * @return list<array{id: string, displayName: string}>
	 */
	public function searchRecipients(string $ownerId, int $bookId, string $query): array {
		$this->bookAccess->getOwned($ownerId, $bookId);

		$query = trim($query);
		if ($query === '') {
			return [];
		}

		$existing = [];
		foreach ($this->shareMapper->findByBook($bookId) as $share) {
			$existing[$share->getSharedWith()] = true;
		}

		$result = [];
		foreach ($this->userManager->searchDisplayName($query, 25) as $user) {
			$uid = $user->getUID();
			if ($uid === $ownerId || isset($existing[$uid]) || !$user->isEnabled()) {
				continue;
			}

			$result[] = [
				'id' => $uid,
				'displayName' => $user->getDisplayName(),
			];
		}

		return $result;
	}

	private function getShareForOwner(string $ownerId, int $bookId, int $shareId): BookShare {
		$share = $this->shareMapper->findByIdAndBook($shareId, $bookId);
		if ($share === null) {
			throw new NotFoundException('Share not found');
		}

		return $share;
	}

	private function validateRole(string $role): string {
		$normalized = BookRole::normalizeShareRole($role);
		if ($normalized === null) {
			throw new ValidationException('The share role is invalid');
		}

		return $normalized;
	}

	/**
	 * @return array{id: int, bookId: int, userId: string, displayName: string, role: string, createdAt: string, updatedAt: string}
	 */
	private function serialize(BookShare $share): array {
		$uid = $share->getSharedWith();

		return [
			'id' => $share->getId(),
			'bookId' => $share->getBookId(),
			'userId' => $uid,
			'displayName' => $this->userManager->getDisplayName($uid) ?? $uid,
			'role' => BookRole::normalizeShareRole($share->getPermission()) ?? BookRole::READ,
			'createdAt' => $share->getCreatedAt()->format(DateTimeInterface::ATOM),
			'updatedAt' => $share->getUpdatedAt()->format(DateTimeInterface::ATOM),
		];
	}
}
