<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\ForbiddenException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookRole;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\BookShareService;
use OCA\BookTailor\Service\ChapterService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class BookShareTest extends TestCase {
	private string $ownerId;
	private string $readerId;
	private string $writerId;
	private IUser $owner;
	private IUser $reader;
	private IUser $writer;
	private BookService $books;
	private BookShareService $shares;
	private ChapterService $chapters;
	private AssetService $assets;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_books') || !$db->tableExists('booktailor_book_shares')) {
			$this->markTestSkipped('BookTailor share tables are not migrated');
		}

		try {
			\OC_App::loadApp('files_sharing');
		} catch (\Throwable) {
			$this->markTestSkipped('files_sharing is not available');
		}

		$this->ownerId = 'bt-share-owner-' . bin2hex(random_bytes(4));
		$this->readerId = 'bt-share-read-' . bin2hex(random_bytes(4));
		$this->writerId = 'bt-share-write-' . bin2hex(random_bytes(4));
		$users = $server->get(IUserManager::class);
		$this->owner = $users->createUser($this->ownerId, 'BookTailor-Test-123!');
		$this->reader = $users->createUser($this->readerId, 'BookTailor-Test-123!');
		$this->writer = $users->createUser($this->writerId, 'BookTailor-Test-123!');
		$server->get(IUserSession::class)->setUser($this->owner);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->shares = $container->get(BookShareService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->assets = $container->get(AssetService::class);
	}

	protected function tearDown(): void {
		try {
			foreach ($this->books->list($this->ownerId) as $book) {
				$this->books->delete($this->ownerId, $book->getId());
			}
		} catch (\Throwable) {
			// Best-effort cleanup.
		}

		$this->reader->delete();
		$this->writer->delete();
		$this->owner->delete();
	}

	public function testReadShareAllowsReadsAndBlocksWrites(): void {
		$book = $this->books->create($this->ownerId, 'Readable');
		$chapter = $this->chapters->create($this->ownerId, $book->getId(), 'Chapter');
		$asset = $this->assets->create($this->ownerId, $chapter->getId(), 'Note')['asset'];
		$this->assets->updateContent($this->ownerId, $asset->getId(), 'hello');

		$this->shares->create($this->ownerId, $book->getId(), $this->readerId, BookRole::READ);

		$accessible = $this->books->listAccessible($this->readerId);
		$this->assertCount(1, $accessible);
		$this->assertSame(BookRole::READ, $accessible[0]['role']);
		$this->assertSame($book->getId(), $accessible[0]['book']->getId());

		$this->assertSame($book->getId(), $this->books->getForRead($this->readerId, $book->getId())->getId());
		$this->assertCount(1, $this->chapters->tree($this->readerId, $book->getId()));
		$this->assertSame('hello', $this->assets->getContent($this->readerId, $asset->getId()));

		$this->expectException(ForbiddenException::class);
		$this->chapters->create($this->readerId, $book->getId(), 'Blocked');
	}

	public function testWriteShareAllowsContentEditingButNotBookAdministration(): void {
		$book = $this->books->create($this->ownerId, 'Writable');
		$this->shares->create($this->ownerId, $book->getId(), $this->writerId, BookRole::WRITE);

		$chapter = $this->chapters->create($this->writerId, $book->getId(), 'Shared chapter');
		$asset = $this->assets->create($this->writerId, $chapter->getId(), 'Shared note')['asset'];
		$this->assets->updateContent($this->writerId, $asset->getId(), 'edited');
		$this->assertSame('edited', $this->assets->getContent($this->writerId, $asset->getId()));

		try {
			$this->books->update($this->writerId, $book->getId(), 'Renamed');
			$this->fail('A write share must not rename the book');
		} catch (NotFoundException) {
			// Expected: only the owner may rename or delete the book.
		}

		$this->expectException(NotFoundException::class);
		$this->shares->create($this->writerId, $book->getId(), $this->readerId, BookRole::READ);
	}

	public function testRevokeRemovesAccessAndNativeShare(): void {
		$book = $this->books->create($this->ownerId, 'Revoked');
		$share = $this->shares->create($this->ownerId, $book->getId(), $this->readerId, BookRole::READ);

		$this->shares->delete($this->ownerId, $book->getId(), $share['id']);
		$this->assertSame([], $this->books->listAccessible($this->readerId));
		$this->assertSame([], $this->shares->listForBook($this->ownerId, $book->getId()));

		$this->expectException(NotFoundException::class);
		$this->books->getForRead($this->readerId, $book->getId());
	}

	public function testRoleCanBeUpdated(): void {
		$book = $this->books->create($this->ownerId, 'Promoted');
		$share = $this->shares->create($this->ownerId, $book->getId(), $this->readerId, BookRole::READ);

		$this->shares->update($this->ownerId, $book->getId(), $share['id'], BookRole::WRITE);
		$updated = $this->books->listAccessible($this->readerId);
		$this->assertSame(BookRole::WRITE, $updated[0]['role']);

		$chapter = $this->chapters->create($this->readerId, $book->getId(), 'Now editable');
		$this->assertSame($book->getId(), $chapter->getBookId());
	}

	public function testInvalidSharesAreRejected(): void {
		$book = $this->books->create($this->ownerId, 'Invalid');

		try {
			$this->shares->create($this->ownerId, $book->getId(), $this->ownerId, BookRole::READ);
			$this->fail('Sharing with the owner must be rejected');
		} catch (\OCA\BookTailor\Exception\ValidationException) {
			// Expected.
		}

		$this->shares->create($this->ownerId, $book->getId(), $this->readerId, BookRole::READ);
		$this->expectException(ConflictException::class);
		$this->shares->create($this->ownerId, $book->getId(), $this->readerId, BookRole::WRITE);
	}
}
