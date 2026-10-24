<?php

declare(strict_types=1);

namespace OCA\Files_Versions\Versions;

use OCP\Files\File;
use OCP\Files\FileInfo;
use OCP\Files\Node;
use OCP\Files\Storage\IStorage;
use OCP\IUser;

interface IVersionBackend {
	public function useBackendForStorage(IStorage $storage): bool;

	/** @return list<IVersion> */
	public function getVersionsForFile(IUser $user, FileInfo $file): array;

	public function createVersion(IUser $user, FileInfo $file);

	public function rollback(IVersion $version);

	public function read(IVersion $version);

	public function getVersionFile(IUser $user, FileInfo $sourceFile, int|string $revision): File;

	public function getRevision(Node $node): int;
}

interface IVersion {
	public function getBackend(): IVersionBackend;

	public function getSourceFile(): FileInfo;

	public function getRevisionId(): int|string;

	public function getTimestamp(): int;

	public function getSize(): int|float;

	public function getSourceFileName(): string;

	public function getMimeType(): string;

	public function getVersionPath(): string;

	public function getUser(): IUser;
}

interface IMetadataVersion {
	/** @return array<string, string> */
	public function getMetadata(): array;

	public function getMetadataValue(string $key): ?string;
}

interface IMetadataVersionBackend {
	public function setMetadataValue(Node $node, int $revision, string $key, string $value): void;
}

interface IVersionManager extends IVersionBackend {
	public function registerBackend(string $storageType, IVersionBackend $backend);

	public function getBackendForStorage(IStorage $storage): IVersionBackend;
}
