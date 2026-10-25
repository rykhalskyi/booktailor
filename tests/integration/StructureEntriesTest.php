<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class StructureEntriesTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private StructureService $structures;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_books')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-entries-' . bin2hex(random_bytes(4));
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

		$this->user->delete();
	}

	public function testInclusionAndOrderAreIndependentPerStructure(): void {
		$book = $this->books->create($this->userId, 'Entries');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$childA = $this->chapters->create($this->userId, $book->getId(), 'Child A', $rootA->getId());
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');

		$entries = $this->entriesByChapter($custom->getId());
		$entries[$rootA->getId()]['included'] = false;
		$entries[$rootA->getId()]['position'] = 2;
		$entries[$rootB->getId()]['position'] = 1;
		$entries[$childA->getId()]['position'] = 1;

		$this->structures->replaceEntries(
			$this->userId,
			$custom->getId(),
			array_values($entries),
		);

		$customTree = $this->chapters->tree($this->userId, $book->getId(), $custom->getId());
		$this->assertCount(2, $customTree);
		$this->assertSame($rootB->getId(), $customTree[0]['chapter']->getId());
		$this->assertSame('1.', $customTree[0]['number']);
		$this->assertSame($childA->getId(), $customTree[1]['chapter']->getId());
		$this->assertSame('2.', $customTree[1]['number']);

		$defaultTree = $this->chapters->tree($this->userId, $book->getId(), $default->getId());
		$this->assertCount(2, $defaultTree);
		$this->assertSame($rootA->getId(), $defaultTree[0]['chapter']->getId());
		$this->assertSame('1.', $defaultTree[0]['number']);
		$this->assertSame($childA->getId(), $defaultTree[0]['children'][0]['chapter']->getId());
		$this->assertSame('1.1', $defaultTree[0]['children'][0]['number']);
		$this->assertSame($rootB->getId(), $defaultTree[1]['chapter']->getId());

		$customEntries = $this->entriesByChapter($custom->getId());
		$this->assertFalse($customEntries[$rootA->getId()]['included']);
		$this->assertTrue($customEntries[$childA->getId()]['included']);
	}

	public function testNewChapterInclusionFollowsTheParent(): void {
		$book = $this->books->create($this->userId, 'New chapters');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');
		$this->exclude($custom->getId(), $root->getId());

		$hiddenChild = $this->chapters->create($this->userId, $book->getId(), 'Hidden child', $root->getId());
		$newRoot = $this->chapters->create($this->userId, $book->getId(), 'New root');

		$defaultEntries = $this->entriesByChapter($default->getId());
		$this->assertTrue($defaultEntries[$hiddenChild->getId()]['included']);
		$this->assertTrue($defaultEntries[$newRoot->getId()]['included']);

		$customEntries = $this->entriesByChapter($custom->getId());
		$this->assertFalse($customEntries[$hiddenChild->getId()]['included']);
		$this->assertTrue($customEntries[$newRoot->getId()]['included']);

		$this->include($custom->getId(), $root->getId());
		$visibleChild = $this->chapters->create($this->userId, $book->getId(), 'Visible child', $root->getId());

		$customEntries = $this->entriesByChapter($custom->getId());
		$this->assertTrue($customEntries[$visibleChild->getId()]['included']);
	}

	public function testEveryChapterMustAppearExactlyOnce(): void {
		$book = $this->books->create($this->userId, 'Validation');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');
		$entries = array_values($this->entriesByChapter($custom->getId()));

		$this->expectException(ValidationException::class);
		$this->structures->replaceEntries($this->userId, $custom->getId(), array_slice($entries, 0, 1));
	}

	public function testDuplicateChaptersAreRejected(): void {
		$book = $this->books->create($this->userId, 'Bad entries');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$custom = $this->structures->create($this->userId, $book->getId(), 'Custom');
		$entries = array_values($this->entriesByChapter($custom->getId()));
		$entries[] = $entries[0];

		$this->expectException(ValidationException::class);
		$this->structures->replaceEntries($this->userId, $custom->getId(), $entries);
	}

	public function testDefaultStructureCannotExcludeChapters(): void {
		$book = $this->books->create($this->userId, 'Default entries');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$default = $this->structures->listForBook($this->userId, $book->getId())[0];
		$entries = array_values($this->entriesByChapter($default->getId()));
		$entries[0]['included'] = false;

		$this->expectException(ValidationException::class);
		$this->structures->replaceEntries($this->userId, $default->getId(), $entries);
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

	private function exclude(int $structureId, int $chapterId): void {
		$this->setIncluded($structureId, $chapterId, false);
	}

	private function include(int $structureId, int $chapterId): void {
		$this->setIncluded($structureId, $chapterId, true);
	}

	private function setIncluded(int $structureId, int $chapterId, bool $included): void {
		$entries = $this->entriesByChapter($structureId);
		$entries[$chapterId]['included'] = $included;
		$this->structures->replaceEntries($this->userId, $structureId, array_values($entries));
	}
}
