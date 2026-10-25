<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StorageService;
use OCA\BookTailor\Service\StructureService;
use OCP\Files\Folder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ChapterMovementTest extends TestCase {
	private string $userId;
	private IUser $user;
	private ?IUser $otherUser = null;
	private BookService $books;
	private ChapterService $chapters;
	private StructureService $structures;
	private StorageService $storage;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_books')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-move-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->structures = $container->get(StructureService::class);
		$this->storage = $container->get(StorageService::class);
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

	public function testDemoteUnderPreviousSiblingAndPromoteBack(): void {
		$book = $this->books->create($this->userId, 'Demote');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$this->chapters->create($this->userId, $book->getId(), 'Root B');
		$childA1 = $this->chapters->create($this->userId, $book->getId(), 'Child A1', $rootA->getId());
		$childA2 = $this->chapters->create($this->userId, $book->getId(), 'Child A2', $rootA->getId());
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');

		$demoted = $this->chapters->move($this->userId, $childA2->getId(), $childA1->getId());
		$this->assertSame($childA1->getId(), $demoted->getParentId());
		$this->assertSame(
			$this->chapterFolderId($childA1),
			$this->chapterParentFolderId($demoted),
		);

		$defaultEntries = $this->entriesByChapter($default->getId());
		$customEntries = $this->entriesByChapter($custom->getId());
		$this->assertSame(1, $defaultEntries[$childA2->getId()]['position']);
		$this->assertSame(1, $customEntries[$childA2->getId()]['position']);

		$promoted = $this->chapters->move($this->userId, $childA2->getId(), $rootA->getId());
		$this->assertSame($rootA->getId(), $promoted->getParentId());
		$this->assertSame(
			$this->chapterFolderId($rootA),
			$this->chapterParentFolderId($promoted),
		);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $default->getId());
		$this->assertSame($childA1->getId(), $tree[0]['children'][0]['chapter']->getId());
		$this->assertSame($childA2->getId(), $tree[0]['children'][1]['chapter']->getId());
	}

	public function testAttachUnderParentsSiblingAndPromoteToRoot(): void {
		$book = $this->books->create($this->userId, 'Attach');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $rootA->getId());
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];

		$attached = $this->chapters->move($this->userId, $child->getId(), $rootB->getId());
		$this->assertSame($rootB->getId(), $attached->getParentId());
		$this->assertSame(
			$this->chapterFolderId($rootB),
			$this->chapterParentFolderId($attached),
		);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $default->getId());
		$this->assertCount(0, $tree[0]['children']);
		$this->assertSame($child->getId(), $tree[1]['children'][0]['chapter']->getId());

		$promoted = $this->chapters->move($this->userId, $child->getId(), null);
		$this->assertNull($promoted->getParentId());
		$this->assertSame(
			$this->storage->getBookFolder($book)->getId(),
			$this->chapterParentFolderId($promoted),
		);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $default->getId());
		$this->assertCount(3, $tree);
		$this->assertSame($child->getId(), $tree[2]['chapter']->getId());
		$this->assertSame('3.', $tree[2]['number']);
	}

	public function testDisallowedMovesAreRejected(): void {
		$book = $this->books->create($this->userId, 'Rejected');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $rootA->getId());
		$grandchildB = $this->chapters->create($this->userId, $book->getId(), 'Grandchild B', $rootB->getId());

		$this->assertMoveRejected(fn () => $this->chapters->move($this->userId, $rootA->getId(), $rootB->getId()));
		$this->assertMoveRejected(fn () => $this->chapters->move($this->userId, $rootA->getId(), $child->getId()));
		$this->assertMoveRejected(fn () => $this->chapters->move($this->userId, $child->getId(), $grandchildB->getId()));
	}

	public function testMoveIsOwnerScoped(): void {
		$book = $this->books->create($this->userId, 'Private move');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());

		$this->otherUser = \OC::$server->get(IUserManager::class)->createUser(
			'bt-move-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);

		$this->expectException(NotFoundException::class);
		$this->chapters->move($this->otherUser->getUID(), $child->getId(), null);
	}

	/**
	 * @return array<int, array{chapterId: int, included: bool, position: int, effectiveParentId: ?int}>
	 */
	private function entriesByChapter(int $structureId): array {
		$entries = [];
		foreach ($this->structures->getEntries($this->userId, $structureId) as $entry) {
			$entries[$entry['chapterId']] = $entry;
		}

		return $entries;
	}

	private function chapterFolderId(Chapter $chapter): int {
		return $this->storage->getChapterFolderForUser($this->userId, $chapter)->getId();
	}

	private function chapterParentFolderId(Chapter $chapter): int {
		$folder = $this->storage->getChapterFolderForUser($this->userId, $chapter);
		$parent = $folder->getParent();
		$this->assertInstanceOf(Folder::class, $parent);

		return $parent->getId();
	}

	private function assertMoveRejected(callable $move): void {
		try {
			$move();
			$this->fail('Expected the move to be rejected');
		} catch (ConflictException) {
			$this->addToAssertionCount(1);
		}
	}
}
