<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Entity\Composition;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\CompositionService;
use OCA\BookTailor\Service\StorageService;
use OCA\BookTailor\Service\StructureService;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class CompositionTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private StructureService $structures;
	private CompositionService $compositions;
	private StorageService $storage;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_compositions')) {
			$this->markTestSkipped('BookTailor composition tables are not migrated');
		}

		$this->userId = 'bt-compose-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->assets = $container->get(AssetService::class);
		$this->structures = $container->get(StructureService::class);
		$this->compositions = $container->get(CompositionService::class);
		$this->storage = $container->get(StorageService::class);
	}

	protected function tearDown(): void {
		try {
			foreach ($this->books->list($this->userId) as $book) {
				$this->books->delete($this->userId, $book->getId());
			}
		} catch (\Throwable) {
			// Best-effort cleanup; deleting the user removes remaining files.
		}

		$this->user->delete();
	}

	public function testOnlyIncludedAssetsAreComposedPerChapter(): void {
		$book = $this->books->create($this->userId, 'Field Notes');
		$part = $this->chapters->create($this->userId, $book->getId(), 'Part One');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Chapter Two', $part->getId());
		$structure = $this->defaultStructure($book);

		$this->asset($book, $part, 'A Summary', 'SUMMARY A', true);
		$this->asset($book, $part, 'A Raw', 'RAW A', false);
		$this->asset($book, $child, 'B Raw', 'RAW B', true);

		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $composition->getId();
		$this->assertSame(Composition::STATUS_QUEUED, $composition->getStatus());

		$this->compositions->run($id);
		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_SUCCEEDED, $composition->getStatus(), (string)$composition->getError());
		$this->assertNull($composition->getError());

		$markdown = $this->compositions->download($this->userId, $id)->getContent();
		$this->assertStringContainsString('# Field Notes', $markdown);
		$this->assertStringContainsString('## 1. Part One', $markdown);
		$this->assertStringContainsString('### 1.1 Chapter Two', $markdown);
		$this->assertStringContainsString('SUMMARY A', $markdown);
		$this->assertStringNotContainsString('RAW A', $markdown);
		$this->assertStringContainsString('RAW B', $markdown);
		$this->assertLessThan(strpos($markdown, 'RAW B'), strpos($markdown, 'SUMMARY A'));
		$this->assertMatchesRegularExpression('/default-\d{8}-\d{6}\.md$/', (string)$composition->getOutputPath());
	}

	public function testIncludedAssetsComposeInNameOrder(): void {
		$book = $this->books->create($this->userId, 'Everything');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);

		$this->asset($book, $chapter, 'Zebra', 'ZEBRA', true);
		$this->asset($book, $chapter, 'Alpha', 'ALPHA', true);

		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $composition->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_SUCCEEDED, $composition->getStatus(), (string)$composition->getError());
		$markdown = $this->compositions->download($this->userId, $id)->getContent();
		$this->assertLessThan(strpos($markdown, 'ZEBRA'), strpos($markdown, 'ALPHA'));
		$this->assertStringContainsString('## 1. Chapter', $markdown);
	}

	public function testImagesAreCopiedAndReferencesRewritten(): void {
		$book = $this->books->create($this->userId, 'Illustrated');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);
		$imageId = $this->image($book, $chapter, 'pic.png', 'PNGDATA');
		$this->asset($book, $chapter, 'Text', '![pic](images/pic.png)' . "\n\n" . '<img src="./images/pic.png">', true);

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $created->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_SUCCEEDED, $composition->getStatus(), (string)$composition->getError());
		$markdown = $this->compositions->download($this->userId, $id)->getContent();
		$name = 'pic-' . $imageId . '.png';
		$this->assertStringContainsString('![pic](images/' . $name . ')', $markdown);
		$this->assertStringContainsString('<img src="images/' . $name . '">', $markdown);

		$images = $this->storage->getCompositionsImagesFolder($book);
		$this->assertTrue($images->nodeExists($name));
		$this->assertSame('PNGDATA', $images->get($name)->getContent());
	}

	public function testMissingImageFailsWithActionableError(): void {
		$book = $this->books->create($this->userId, 'Broken Image');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);
		$this->asset($book, $chapter, 'Text', '![missing](images/nope.png)', true);

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $created->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_FAILED, $composition->getStatus());
		$this->assertNull($composition->getOutputFileId());
		$this->assertStringContainsString('nope.png', (string)$composition->getError());
		$this->assertStringContainsString('missing', (string)$composition->getError());
	}

	public function testNoIncludedAssetsFailsAndFailedCompositionCanBeRetried(): void {
		$book = $this->books->create($this->userId, 'No Match');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);
		$this->asset($book, $chapter, 'Text', 'CONTENT', false);

		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $composition->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_FAILED, $composition->getStatus());
		$this->assertSame('No assets are marked for composition', $composition->getError());

		$retried = $this->compositions->retry($this->userId, $id);
		$this->assertSame(Composition::STATUS_QUEUED, $retried->getStatus());
		$this->assertNull($retried->getError());

		$this->compositions->run($id);
		$this->assertSame(Composition::STATUS_FAILED, $this->compositions->getOwned($this->userId, $id)->getStatus());

		$this->compositions->delete($this->userId, $id);
		$this->expectException(NotFoundException::class);
		$this->compositions->getOwned($this->userId, $id);
	}

	public function testDeletingSucceededCompositionRemovesTheOutput(): void {
		$book = $this->books->create($this->userId, 'Delete Me');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);
		$this->asset($book, $chapter, 'Text', 'CONTENT', true);

		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId());
		$id = $composition->getId();
		$this->compositions->run($id);
		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_SUCCEEDED, $composition->getStatus());
		$outputId = $composition->getOutputFileId();
		$this->assertNotNull($outputId);

		try {
			$this->compositions->retry($this->userId, $id);
			$this->fail('A succeeded composition must not be retried');
		} catch (ConflictException) {
			// Expected.
		}

		$this->compositions->delete($this->userId, $id);
		$this->assertNull($this->storage->findNodeById($this->userId, $outputId, \OCP\Files\File::class));
	}

	public function testCompositionIsOwnerScoped(): void {
		$book = $this->books->create($this->userId, 'Private');
		$structure = $this->defaultStructure($book);
		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId());

		$this->expectException(NotFoundException::class);
		$this->compositions->getOwned('somebody-else', $composition->getId());
	}

	public function testCreateValidatesStructureAndName(): void {
		$book = $this->books->create($this->userId, 'Validation');
		$other = $this->books->create($this->userId, 'Other');
		$structure = $this->defaultStructure($book);
		$otherStructure = $this->defaultStructure($other);

		try {
			$this->compositions->create($this->userId, $book->getId(), $otherStructure->getId());
			$this->fail('A structure from another book must be rejected');
		} catch (NotFoundException) {
			// Expected.
		}

		$composition = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), 'Final');
		$this->assertSame('Final', $composition->getOutputName());

		$this->expectException(ValidationException::class);
		$this->compositions->create($this->userId, $book->getId(), $structure->getId(), str_repeat('a', 256));
	}

	private function asset(Book $book, Chapter $chapter, string $name, string $content, bool $includeInComposition): Asset {
		$created = $this->assets->create($this->userId, $chapter->getId(), $name);
		$asset = $created['asset'];
		$this->assets->updateContent($this->userId, $asset->getId(), $content);
		if ($includeInComposition) {
			$asset = $this->assets->setIncludeInComposition($this->userId, $asset->getId(), true)['asset'];
		}

		return $asset;
	}

	private function defaultStructure(Book $book): \OCA\BookTailor\Entity\Structure {
		$structures = $this->structures->listForBook($this->userId, $book->getId());

		return $structures[0];
	}

	private function image(Book $book, Chapter $chapter, string $name, string $content): int {
		$chapterFolder = $this->storage->getChapterFolderForUser($this->userId, $chapter);
		if (!$chapterFolder->nodeExists('images')) {
			$chapterFolder->newFolder('images');
		}
		$images = $chapterFolder->get('images');
		$this->assertInstanceOf(Folder::class, $images);
		$file = $images->newFile($name, $content);

		return $file->getId();
	}
}
