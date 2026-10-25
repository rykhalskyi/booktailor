<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class StructureCrudTest extends TestCase {
	private string $userId;
	private IUser $user;
	private ?IUser $otherUser = null;
	private BookService $books;
	private ChapterService $chapters;
	private StructureService $structures;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_books')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-structures-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->structures = $container->get(StructureService::class);
	}

	protected function tearDown(): void {
		try {
			foreach ($this->books->list($this->userId) as $book) {
				$this->books->delete($this->userId, $book->getId());
			}
		} catch (\Throwable) {
			// Best-effort cleanup; deleting the user removes the remaining files.
		}

		$this->otherUser?->delete();
		$this->user->delete();
	}

	public function testCreateCopiesDefaultEntries(): void {
		$book = $this->books->create($this->userId, 'Structures');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];

		$structure = $this->structures->create($this->userId, $book->getId(), 'Study');

		$this->assertSame('Study', $structure->getName());
		$this->assertFalse($structure->getIsDefault());
		$this->assertCount(2, $this->structures->listForBook($this->userId, $book->getId()));

		$defaultEntries = $this->entriesByChapter($default->getId());
		$copiedEntries = $this->entriesByChapter($structure->getId());

		$this->assertCount(2, $copiedEntries);
		$this->assertSame(
			array_keys($defaultEntries),
			array_keys($copiedEntries),
		);
		foreach ($defaultEntries as $chapterId => $entry) {
			$this->assertSame($entry['included'], $copiedEntries[$chapterId]['included']);
			$this->assertSame($entry['position'], $copiedEntries[$chapterId]['position']);
		}
	}

	public function testDuplicateStructureNameIsRejected(): void {
		$book = $this->books->create($this->userId, 'Duplicates');
		$this->structures->create($this->userId, $book->getId(), 'Notes');

		$this->expectException(ConflictException::class);
		$this->structures->create($this->userId, $book->getId(), 'Notes');
	}

	public function testRenameAndDeleteCustomStructure(): void {
		$book = $this->books->create($this->userId, 'Lifecycle');
		$structure = $this->structures->create($this->userId, $book->getId(), 'Draft');

		$renamed = $this->structures->rename($this->userId, $structure->getId(), 'Final');
		$this->assertSame('Final', $renamed->getName());

		$this->structures->delete($this->userId, $structure->getId());

		$this->assertCount(1, $this->structures->listForBook($this->userId, $book->getId()));
		$this->expectException(NotFoundException::class);
		$this->structures->getOwned($this->userId, $structure->getId());
	}

	public function testDefaultStructureCannotBeDeleted(): void {
		$book = $this->books->create($this->userId, 'Protected');
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->expectException(ConflictException::class);
		$this->structures->delete($this->userId, $default->getId());
	}

	public function testStructureWritesAreOwnerScoped(): void {
		$book = $this->books->create($this->userId, 'Private');
		$structure = $this->structures->create($this->userId, $book->getId(), 'Mine');

		$this->otherUser = \OC::$server->get(IUserManager::class)->createUser(
			'bt-structures-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);
		$otherId = $this->otherUser->getUID();

		$this->expectException(NotFoundException::class);
		$this->structures->rename($otherId, $structure->getId(), 'Stolen');
	}

	/**
	 * @return array<int, array{chapterId: int, included: bool, position: int, effectiveParentId: ?int}>
	 */
	private function entriesByChapter(int $structureId): array {
		$entries = [];
		foreach ($this->structures->getEntries($this->userId, $structureId) as $entry) {
			$entries[$entry['chapterId']] = $entry;
		}
		ksort($entries);

		return $entries;
	}
}
