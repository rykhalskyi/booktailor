<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StorageService;
use OCA\BookTailor\Service\StructureService;
use OCA\BookTailor\Service\TagService;
use OCA\BookTailor\Service\VersionService;
use OCA\Files_Versions\Versions\IVersionManager;
use OCP\App\IAppManager;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class CoreDomainTest extends TestCase {
	private string $userId;
	private IUser $user;
	private ?IUser $otherUser = null;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private TagService $tags;
	private StructureService $structures;
	private VersionService $versions;
	private StorageService $storage;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_books')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-test-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->assets = $container->get(AssetService::class);
		$this->tags = $container->get(TagService::class);
		$this->structures = $container->get(StructureService::class);
		$this->versions = $container->get(VersionService::class);
		$this->storage = $container->get(StorageService::class);
	}

	protected function tearDown(): void {
		try {
			foreach ($this->books->list($this->userId) as $book) {
				$this->books->delete($this->userId, $book->getId());
			}
		} catch (\Throwable) {
			// Best-effort cleanup; the user deletion removes the remaining files.
		}

		$this->otherUser?->delete();
		$this->user->delete();
	}

	public function testBookChapterAssetLifecycle(): void {
		$book = $this->books->create($this->userId, 'Lifecycle', 'description');
		$this->assertSame($this->userId, $book->getUserId());
		$this->assertNotNull($book->getRootFileId());
		$this->assertCount(1, $this->books->list($this->userId));

		$book = $this->books->update($this->userId, $book->getId(), 'Lifecycle Renamed');
		$this->assertSame('Lifecycle Renamed', $book->getName());
		$this->assertSame('description', $book->getDescription());

		$structures = $this->structures->listForBook($this->userId, $book->getId());
		$this->assertCount(1, $structures);
		$this->assertTrue($structures[0]->getIsDefault());

		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$child = $this->chapters->rename($this->userId, $child->getId(), 'Child Renamed');
		$this->assertSame('Child Renamed', $child->getName());
		$this->assertSame('Child Renamed', $this->storage->getChapterFolderForUser($this->userId, $child)->getName());

		$tree = $this->chapters->tree($this->userId, $book->getId());
		$this->assertCount(1, $tree);
		$this->assertSame($root->getId(), $tree[0]['chapter']->getId());
		$this->assertSame($child->getId(), $tree[0]['children'][0]['chapter']->getId());

		$structureTree = $this->chapters->tree(
			$this->userId,
			$book->getId(),
			$structures[0]->getId(),
		);
		$this->assertCount(1, $structureTree);
		$this->assertSame($root->getId(), $structureTree[0]['chapter']->getId());

		$entries = $this->structures->getEntries($this->userId, $structures[0]->getId());
		$this->assertCount(2, $entries);
		$entryByChapter = [];
		foreach ($entries as $entry) {
			$entryByChapter[$entry['chapterId']] = $entry;
		}
		$this->assertNull($entryByChapter[$root->getId()]['effectiveParentId']);
		$this->assertSame($root->getId(), $entryByChapter[$child->getId()]['effectiveParentId']);

		$created = $this->assets->create($this->userId, $child->getId(), 'Raw');
		$asset = $created['asset'];
		$this->assertSame('Raw', $asset->getName());
		$this->assertSame('Raw.md', $asset->getFileName());

		$renamed = $this->assets->rename($this->userId, $asset->getId(), 'Raw Renamed');
		$asset = $renamed['asset'];
		$this->assertSame('Raw Renamed', $asset->getName());
		$this->assertSame('Raw Renamed.md', $asset->getFileName());

		$this->assets->updateContent($this->userId, $asset->getId(), "# Raw\n\nHello\n");
		$this->assertSame("# Raw\n\nHello\n", $this->assets->getContent($this->userId, $asset->getId()));

		$tag = $this->tags->create($this->userId, $book->getId(), 'source');
		$assigned = $this->tags->replaceForAsset($this->userId, $asset->getId(), [$tag->getId()]);
		$this->assertSame([$tag->getId()], array_map(static fn ($t): int => $t->getId(), $assigned));
		$this->assertSame([$tag->getId()], array_map(
			static fn ($t): int => $t->getId(),
			$this->tags->listForAsset($this->userId, $asset->getId()),
		));

		$this->tags->delete($this->userId, $tag->getId());
		$this->assertSame([], $this->tags->listForAsset($this->userId, $asset->getId()));

		$assetContext = $this->assets->get($this->userId, $asset->getId());
		$this->assertSame($asset->getId(), $assetContext['asset']->getId());
		$this->assertSame($book->getId(), $assetContext['bookId']);

		$this->assets->delete($this->userId, $asset->getId());
		$this->expectException(NotFoundException::class);
		$this->assets->get($this->userId, $asset->getId());
	}

	public function testOwnershipIsolation(): void {
		$book = $this->books->create($this->userId, 'Private');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$asset = $this->assets->create($this->userId, $chapter->getId(), 'Note')['asset'];
		$tag = $this->tags->create($this->userId, $book->getId(), 'private-tag');

		$this->otherUser = \OC::$server->get(IUserManager::class)->createUser(
			'bt-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);
		$otherId = $this->otherUser->getUID();

		$this->expectException(NotFoundException::class);
		$this->books->get($otherId, $book->getId());
	}

	public function testChapterAndAssetCollisions(): void {
		$book = $this->books->create($this->userId, 'Collisions');
		$first = $this->chapters->create($this->userId, $book->getId(), 'Same');
		$second = $this->chapters->create($this->userId, $book->getId(), 'Same');

		$firstFolder = $this->storage->getChapterFolderForUser($this->userId, $first);
		$secondFolder = $this->storage->getChapterFolderForUser($this->userId, $second);
		$this->assertSame('Same', $firstFolder->getName());
		$this->assertSame('Same (2)', $secondFolder->getName());

		$asset = $this->assets->create($this->userId, $first->getId(), 'Note')['asset'];
		$this->expectException(ValidationException::class);
		$this->assets->create($this->userId, $first->getId(), 'Note');
	}

	public function testTagCollision(): void {
		$book = $this->books->create($this->userId, 'Tags');
		$this->tags->create($this->userId, $book->getId(), 'Source');

		$this->expectException(ConflictException::class);
		$this->tags->create($this->userId, $book->getId(), 'source');
	}

	public function testChapterDeleteCascadesAssetsAndFolders(): void {
		$book = $this->books->create($this->userId, 'Cascade');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$asset = $this->assets->create($this->userId, $child->getId(), 'Note')['asset'];
		$assetFileId = $asset->getFileId();
		$childFileId = $child->getFileId();
		$this->assertNotNull($assetFileId);
		$this->assertNotNull($childFileId);

		$this->chapters->delete($this->userId, $root->getId());

		$this->assertNull($this->storage->findNodeById($this->userId, $assetFileId));
		$this->assertNull($this->storage->findNodeById($this->userId, $childFileId));
		$this->expectException(NotFoundException::class);
		$this->assets->get($this->userId, $asset->getId());
	}

	public function testBookDeleteMovesFolderToTrash(): void {
		$book = $this->books->create($this->userId, 'DeleteMe');
		$rootFileId = $book->getRootFileId();
		$this->assertNotNull($rootFileId);

		$this->books->delete($this->userId, $book->getId());

		$this->assertNull($this->storage->findNodeById($this->userId, $rootFileId));
	}

	public function testVersionCheckInAndRestore(): void {
		$appManager = \OC::$server->get(IAppManager::class);
		if (!$appManager->isEnabledForUser('files_versions', $this->user)) {
			$this->markTestSkipped('files_versions is disabled');
		}

		$book = $this->books->create($this->userId, 'Versions');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$asset = $this->assets->create($this->userId, $chapter->getId(), 'Draft')['asset'];
		$this->assets->updateContent($this->userId, $asset->getId(), "# Draft\n\nv1\n");

		$file = $this->storage->getAssetFile($this->userId, $asset);
		$versionManager = \OC::$server->get(IVersionManager::class);
		$versionManager->createVersion($this->user, $file);
		sleep(1);
		$this->assets->updateContent($this->userId, $asset->getId(), "# Draft\n\nv2\n");

		$checkIn = $this->versions->checkIn($this->userId, $asset->getId(), 'ready');
		$this->assertSame('ready', $checkIn['message']);
		$this->assertTrue($checkIn['isCurrent']);

		$history = $this->versions->list($this->userId, $asset->getId());
		$this->assertGreaterThanOrEqual(2, count($history));
		$current = $history[0];
		$this->assertTrue($current['isCurrent']);
		$this->assertSame('ready', $current['message']);

		$historical = null;
		foreach ($history as $version) {
			if (!$version['isCurrent']) {
				$historical = $version;
				break;
			}
		}
		$this->assertNotNull($historical);

		$restored = $this->versions->restore($this->userId, $asset->getId(), $historical['revisionId']);
		$this->assertTrue($restored['isCurrent']);
		$this->assertStringContainsString('v1', $this->assets->getContent($this->userId, $asset->getId()));
	}
}
