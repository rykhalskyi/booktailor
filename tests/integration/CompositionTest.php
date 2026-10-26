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
use OCA\BookTailor\Service\TagService;
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
	private TagService $tags;
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
		$this->tags = $container->get(TagService::class);
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

	public function testRuleSelectionFallsBackPerChapter(): void {
		$book = $this->books->create($this->userId, 'Field Notes');
		$part = $this->chapters->create($this->userId, $book->getId(), 'Part One');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Chapter Two', $part->getId());
		$structure = $this->defaultStructure($book);

		$summary = $this->tag($book, 'summary');
		$raw = $this->tag($book, 'raw');
		$this->asset($book, $part, 'A Summary', 'SUMMARY A', [$summary]);
		$this->asset($book, $part, 'A Raw', 'RAW A', [$raw]);
		$this->asset($book, $child, 'B Raw', 'RAW B', [$raw]);

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), [
			['tagId' => $summary, 'priority' => 1, 'fallback' => false],
			['tagId' => $raw, 'priority' => 2, 'fallback' => true],
		]);
		$id = $created['composition']->getId();
		$this->assertSame(Composition::STATUS_QUEUED, $created['composition']->getStatus());

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

	public function testEmptyRulesComposeAllAssetsInNameOrder(): void {
		$book = $this->books->create($this->userId, 'Everything');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);

		$this->asset($book, $chapter, 'Zebra', 'ZEBRA');
		$this->asset($book, $chapter, 'Alpha', 'ALPHA');

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), []);
		$id = $created['composition']->getId();
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
		$this->asset($book, $chapter, 'Text', '![pic](images/pic.png)' . "\n\n" . '<img src="./images/pic.png">');

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), []);
		$id = $created['composition']->getId();
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
		$this->asset($book, $chapter, 'Text', '![missing](images/nope.png)');

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), []);
		$id = $created['composition']->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_FAILED, $composition->getStatus());
		$this->assertNull($composition->getOutputFileId());
		$this->assertStringContainsString('nope.png', (string)$composition->getError());
		$this->assertStringContainsString('missing', (string)$composition->getError());
	}

	public function testNoMatchingAssetsFailsAndFailedCompositionCanBeRetried(): void {
		$book = $this->books->create($this->userId, 'No Match');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');
		$structure = $this->defaultStructure($book);
		$tag = $this->tag($book, 'wanted');
		$this->asset($book, $chapter, 'Text', 'CONTENT');

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), [
			['tagId' => $tag, 'priority' => 1, 'fallback' => false],
		]);
		$id = $created['composition']->getId();
		$this->compositions->run($id);

		$composition = $this->compositions->getOwned($this->userId, $id);
		$this->assertSame(Composition::STATUS_FAILED, $composition->getStatus());
		$this->assertSame('No assets matched the composition rules', $composition->getError());

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
		$this->asset($book, $chapter, 'Text', 'CONTENT');

		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), []);
		$id = $created['composition']->getId();
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
		$created = $this->compositions->create($this->userId, $book->getId(), $structure->getId(), []);

		$this->expectException(NotFoundException::class);
		$this->compositions->getOwned('somebody-else', $created['composition']->getId());
	}

	public function testCreateValidatesStructureTagsAndName(): void {
		$book = $this->books->create($this->userId, 'Validation');
		$other = $this->books->create($this->userId, 'Other');
		$structure = $this->defaultStructure($book);
		$otherStructure = $this->defaultStructure($other);

		try {
			$this->compositions->create($this->userId, $book->getId(), $otherStructure->getId(), []);
			$this->fail('A structure from another book must be rejected');
		} catch (NotFoundException) {
			// Expected.
		}

		$foreignTag = $this->tag($other, 'foreign');

		try {
			$this->compositions->create($this->userId, $book->getId(), $structure->getId(), [
				['tagId' => $foreignTag, 'priority' => 1, 'fallback' => false],
			]);
			$this->fail('A tag from another book must be rejected');
		} catch (ValidationException) {
			// Expected.
		}

		$tag = $this->tag($book, 'local');
		try {
			$this->compositions->create($this->userId, $book->getId(), $structure->getId(), [
				['tagId' => $tag, 'priority' => 1, 'fallback' => false],
				['tagId' => $tag, 'priority' => 2, 'fallback' => false],
			]);
			$this->fail('Duplicate rule tags must be rejected');
		} catch (ValidationException) {
			// Expected.
		}

		$this->expectException(ValidationException::class);
		$this->compositions->create($this->userId, $book->getId(), $structure->getId(), [], str_repeat('a', 256));
	}

	/**
	 * @param list<int> $tagIds
	 */
	private function asset(Book $book, Chapter $chapter, string $name, string $content, array $tagIds = []): Asset {
		$created = $this->assets->create($this->userId, $chapter->getId(), $name);
		$asset = $created['asset'];
		$this->assets->updateContent($this->userId, $asset->getId(), $content);
		if ($tagIds !== []) {
			$this->tags->replaceForAsset($this->userId, $asset->getId(), $tagIds);
		}

		return $asset;
	}

	private function tag(Book $book, string $name): int {
		return $this->tags->create($this->userId, $book->getId(), $name)->getId();
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
