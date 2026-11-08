<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

class FileShareService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IShareManager $shareManager,
		private readonly StorageService $storage,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return array{share: IShare, created: bool}
	 */
	public function createUserShare(Book $book, string $targetUid, string $role): array {
		if (!$this->shareManager->shareApiEnabled()) {
			throw new ConflictException('Nextcloud sharing is not enabled on this server');
		}

		$ownerId = $book->getUserId();
		$folder = $this->storage->getBookFolder($ownerId, $book);
		$existing = $this->findShareForFolder($ownerId, $folder, $targetUid);
		$permissions = $this->permissionsForRole($role);

		if ($existing !== null) {
			// Never adopt a share BookTailor does not manage: doing so would change
			// permissions the owner set elsewhere and revoke it on BookTailor delete.
			throw new ConflictException('This book is already shared with this user outside BookTailor. Remove the existing share first.');
		}

		$share = $this->shareManager->newShare();
		$share->setNode($folder);
		$share->setShareType(IShare::TYPE_USER);
		$share->setSharedWith($targetUid);
		$share->setSharedBy($ownerId);
		$share->setShareOwner($ownerId);
		$share->setPermissions($permissions);

		try {
			$created = $this->shareManager->createShare($share);
		} catch (\Throwable $e) {
			$this->logger->error('Could not create a Nextcloud book share', [
				'bookId' => $book->getId(),
				'targetUid' => $targetUid,
				'exception' => $e,
			]);
			throw new ConflictException('The book could not be shared with this user');
		}

		return ['share' => $created, 'created' => true];
	}

	/** @psalm-suppress PossiblyUnusedReturnValue */
	public function updateUserShare(string $ownerId, string $shareId, string $role): IShare {
		$share = $this->getShare($ownerId, $shareId);
		$share->setPermissions($this->permissionsForRole($role));

		return $this->shareManager->updateShare($share);
	}

	public function deleteUserShare(string $ownerId, string $shareId): void {
		try {
			$share = $this->getShare($ownerId, $shareId);
		} catch (NotFoundException) {
			return;
		}

		try {
			$this->shareManager->deleteShare($share);
		} catch (ShareNotFound) {
			// The native share is already gone.
		} catch (\Throwable $e) {
			$this->logger->error('Could not delete a Nextcloud book share', [
				'shareId' => $shareId,
				'exception' => $e,
			]);
			throw new ConflictException('The share could not be revoked');
		}
	}

	public function permissionsForRole(string $role): int {
		if ($role === BookRole::WRITE) {
			return Constants::PERMISSION_READ
				| Constants::PERMISSION_UPDATE
				| Constants::PERMISSION_CREATE
				| Constants::PERMISSION_DELETE;
		}

		return Constants::PERMISSION_READ;
	}

	private function getShare(string $ownerId, string $shareId): IShare {
		try {
			return $this->shareManager->getShareById($shareId, $ownerId);
		} catch (ShareNotFound) {
			throw new NotFoundException('Share not found');
		}
	}

	private function findShareForFolder(string $ownerId, Folder $folder, string $targetUid): ?IShare {
		foreach ($this->shareManager->getSharesBy($ownerId, IShare::TYPE_USER, $folder, false, -1) as $share) {
			if ($share->getSharedWith() === $targetUid) {
				return $share;
			}
		}

		return null;
	}
}
