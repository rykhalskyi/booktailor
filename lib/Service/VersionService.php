<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTimeImmutable;
use DateTimeInterface;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\Files_Versions\Versions\IMetadataVersion;
use OCA\Files_Versions\Versions\IMetadataVersionBackend;
use OCA\Files_Versions\Versions\IVersion;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class VersionService {
	private const MESSAGE_KEY = 'booktailor_message';
	private const AUTHOR_KEY = 'booktailor_author';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly AssetService $assetService,
		private readonly StorageService $storage,
		private readonly IUserManager $userManager,
		private readonly IAppManager $appManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return list<array{revisionId: string, author: ?string, message: ?string, size: int, createdAt: string, isCurrent: bool}>
	 */
	public function list(string $userId, int $assetId): array {
		$context = $this->assetService->context($userId, $assetId);
		$user = $this->getUser($userId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);
		$versions = $this->getVersions($user, $file);

		usort($versions, static function (IVersion $a, IVersion $b): int {
			return $b->getTimestamp() <=> $a->getTimestamp()
				?: ((string)$b->getRevisionId() <=> (string)$a->getRevisionId());
		});

		return array_map(
			fn (IVersion $version): array => $this->serializeVersion($version, $file),
			$versions,
		);
	}

	/**
	 * Attach a check-in message to the current native revision (D-21).
	 *
	 * @return array{revisionId: string, author: ?string, message: ?string, size: int, createdAt: string, isCurrent: bool}
	 */
	public function checkIn(string $userId, int $assetId, ?string $message): array {
		$context = $this->assetService->context($userId, $assetId);
		$user = $this->getUser($userId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);

		// getVersionsForFile() also upserts the current revision into the native
		// files_versions table. setMetadataValue() requires that row to exist.
		$this->getVersions($user, $file);
		$currentRevision = $file->getMTime();
		$backend = $this->backend($user, $file);

		$message = $message === null ? null : trim($message);
		try {
			if ($message !== null && $message !== '') {
				$backend->setMetadataValue($file, $currentRevision, self::MESSAGE_KEY, $message);
			}
			$backend->setMetadataValue($file, $currentRevision, self::AUTHOR_KEY, $user->getDisplayName());
		} catch (\Throwable $e) {
			$this->logger->error('Failed to write version metadata', [
				'exception' => $e,
				'assetId' => $assetId,
			]);
			throw new ConflictException('Could not store the check-in message');
		}

		foreach ($this->getVersions($user, $file) as $version) {
			if ((string)$version->getRevisionId() === (string)$currentRevision) {
				return $this->serializeVersion($version, $file);
			}
		}

		throw new ConflictException('Current revision is not available');
	}

	/**
	 * @return array{revisionId: string, author: ?string, message: ?string, size: int, createdAt: string, isCurrent: bool}
	 */
	public function restore(string $userId, int $assetId, string $revisionId): array {
		$context = $this->assetService->context($userId, $assetId);
		$user = $this->getUser($userId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);
		$versions = $this->getVersions($user, $file);
		$version = $this->findVersion($versions, $revisionId);
		if ($version === null) {
			throw new NotFoundException('Version not found');
		}
		if ((string)$version->getRevisionId() === (string)$file->getMTime()) {
			throw new ConflictException('The current revision cannot be restored');
		}

		$this->manager($user)->rollback($version);

		return $this->serializeVersion($version, $file, true);
	}

	/** @psalm-suppress PossiblyUnusedMethod */
	public function getContent(string $userId, int $assetId, string $revisionId): string {
		$context = $this->assetService->context($userId, $assetId);
		$user = $this->getUser($userId);
		$file = $this->storage->getAssetFile($userId, $context['asset']);
		$versions = $this->getVersions($user, $file);
		$version = $this->findVersion($versions, $revisionId);
		if ($version === null) {
			throw new NotFoundException('Version not found');
		}
		if ((string)$version->getRevisionId() === (string)$file->getMTime()) {
			throw new ConflictException('The current revision is the asset content');
		}

		$handle = $this->manager($user)->read($version);
		if (!is_resource($handle)) {
			throw new NotFoundException('Version content not found');
		}

		try {
			$content = stream_get_contents($handle);
		} finally {
			fclose($handle);
		}

		return $content === false ? '' : $content;
	}

	/**
	 * @return list<IVersion>
	 */
	private function getVersions(IUser $user, File $file): array {
		$manager = $this->manager($user);

		return $manager->getVersionsForFile($user, $file);
	}

	private function manager(IUser $user): IVersionManager {
		if (!$this->appManager->isEnabledForUser('files_versions', $user)) {
			throw new ConflictException('File versions are not available');
		}

		try {
			$manager = $this->container->get(IVersionManager::class);
		} catch (\Throwable $e) {
			$this->logger->warning('Files versions service is not available', ['exception' => $e]);
			throw new ConflictException('File versions are not available');
		}

		if (!$manager instanceof IVersionManager) {
			throw new ConflictException('File versions are not available');
		}

		return $manager;
	}

	private function backend(IUser $user, File $file): IMetadataVersionBackend {
		$manager = $this->manager($user);

		try {
			$backend = $manager->getBackendForStorage($file->getStorage());
		} catch (\Throwable $e) {
			$this->logger->warning('Version backend is not available', ['exception' => $e]);
			throw new ConflictException('Check-in messages are not supported');
		}

		if (!$backend instanceof IMetadataVersionBackend) {
			throw new ConflictException('Check-in messages are not supported');
		}

		return $backend;
	}

	/**
	 * @param list<IVersion> $versions
	 */
	private function findVersion(array $versions, string $revisionId): ?IVersion {
		foreach ($versions as $version) {
			if ((string)$version->getRevisionId() === $revisionId) {
				return $version;
			}
		}

		return null;
	}

	/**
	 * @return array{revisionId: string, author: ?string, message: ?string, size: int, createdAt: string, isCurrent: bool}
	 */
	private function serializeVersion(IVersion $version, File $file, ?bool $forceCurrent = null): array {
		$revisionId = (string)$version->getRevisionId();
		$metadata = $version instanceof IMetadataVersion ? $version->getMetadata() : [];
		$message = $metadata[self::MESSAGE_KEY] ?? null;
		$author = $metadata['author'] ?? $metadata[self::AUTHOR_KEY] ?? null;

		return [
			'revisionId' => $revisionId,
			'author' => $author !== null && $author !== '' ? $author : null,
			'message' => $message !== null && $message !== '' ? $message : null,
			'size' => (int)$version->getSize(),
			'createdAt' => (new DateTimeImmutable('@' . $version->getTimestamp()))->format(DateTimeInterface::ATOM),
			'isCurrent' => $forceCurrent ?? ($revisionId === (string)$file->getMTime()),
		];
	}

	private function getUser(string $userId): IUser {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new NotFoundException('User not found');
		}

		return $user;
	}
}
