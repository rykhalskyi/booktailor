<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class StructureCascadeTest extends TestCase {
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

		$this->userId = 'bt-cascade-' . bin2hex(random_bytes(4));
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

		if ($this->otherUser !== null) {
			try {
				foreach ($this->books->list($this->otherUser->getUID()) as $book) {
					$this->books->delete($this->otherUser->getUID(), $book->getId());
				}
			} catch (\Throwable) {
				// Best-effort cleanup.
			}
			$this->otherUser->delete();
		}

		$this->user->delete();
	}

	public function testDeletingChaptersRemovesEntriesFromEveryStructure(): void {
		$book = $this->books->create($this->userId, 'Cascade');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');

		$this->assertCount(2, $this->structures->getEntries($this->userId, $custom->getId()));

		$this->chapters->delete($this->userId, $child->getId());

		$customEntries = $this->entriesByChapter($custom->getId());
		$defaultEntries = $this->entriesByChapter($default->getId());
		$this->assertCount(1, $customEntries);
		$this->assertArrayHasKey($root->getId(), $customEntries);
		$this->assertArrayNotHasKey($child->getId(), $customEntries);
		$this->assertArrayNotHasKey($child->getId(), $defaultEntries);

		$this->chapters->delete($this->userId, $root->getId());

		$this->assertSame([], $this->entriesByChapter($custom->getId()));
	}

	public function testStructureTreeRejectsAStructureFromAnotherBook(): void {
		$bookA = $this->books->create($this->userId, 'Book A');
		$bookB = $this->books->create($this->userId, 'Book B');
		$structureB = $this->structures->listForBook($this->userId, $bookB->getId())[0];

		$this->expectException(NotFoundException::class);
		$this->chapters->tree($this->userId, $bookA->getId(), $structureB->getId());
	}

	public function testEntriesOfAnotherUserAreNotVisible(): void {
		$this->otherUser = \OC::$server->get(IUserManager::class)->createUser(
			'bt-cascade-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);
		$otherBook = $this->books->create($this->otherUser->getUID(), 'Other book');
		$otherStructure = $this->structures->listForBook($this->otherUser->getUID(), $otherBook->getId())[0];

		$this->expectException(NotFoundException::class);
		$this->structures->getEntries($this->userId, $otherStructure->getId());
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
