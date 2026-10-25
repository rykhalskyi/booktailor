<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class StructureTreeNumberingTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private StructureService $structures;
	private IDBConnection $db;

	protected function setUp(): void {
		$server = \OC::$server;
		$this->db = $server->get(IDBConnection::class);
		if (!$this->db->tableExists('booktailor_books')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-tree-' . bin2hex(random_bytes(4));
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

	public function testStructureTreeUsesDraftNumbering(): void {
		$book = $this->books->create($this->userId, 'Numbering');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$childA1 = $this->chapters->create($this->userId, $book->getId(), 'Child A1', $rootA->getId());
		$this->chapters->create($this->userId, $book->getId(), 'Child A2', $rootA->getId());
		$this->chapters->create($this->userId, $book->getId(), 'Grandchild', $childA1->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());

		$this->assertCount(2, $tree);
		$this->assertSame($rootA->getId(), $tree[0]['chapter']->getId());
		$this->assertSame('1.', $tree[0]['number']);
		$this->assertSame($rootB->getId(), $tree[1]['chapter']->getId());
		$this->assertSame('2.', $tree[1]['number']);

		$this->assertCount(2, $tree[0]['children']);
		$this->assertSame('1.1', $tree[0]['children'][0]['number']);
		$this->assertSame('1.2', $tree[0]['children'][1]['number']);
		$this->assertSame('1.1.1', $tree[0]['children'][0]['children'][0]['number']);

		$canonical = $this->chapters->tree($this->userId, $book->getId());
		$this->assertArrayNotHasKey('number', $canonical[0]);
	}

	public function testExcludedAncestorsMoveChildrenToTheTopLevel(): void {
		$book = $this->books->create($this->userId, 'Orphans');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$this->chapters->create($this->userId, $book->getId(), 'Grandchild', $child->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->setIncluded($structure->getId(), $root->getId(), false);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertCount(1, $tree);
		$this->assertSame($child->getId(), $tree[0]['chapter']->getId());
		$this->assertSame('1.', $tree[0]['number']);
		$this->assertSame('1.1', $tree[0]['children'][0]['number']);

		$entries = $this->entriesByChapter($structure->getId());
		$this->assertNull($entries[$child->getId()]['effectiveParentId']);
		$this->assertSame($child->getId(), $entries[$tree[0]['children'][0]['chapter']->getId()]['effectiveParentId']);
	}

	public function testExcludedMiddleChapterUsesNearestIncludedAncestor(): void {
		$book = $this->books->create($this->userId, 'Middle');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$child = $this->chapters->create($this->userId, $book->getId(), 'Child', $root->getId());
		$grandchild = $this->chapters->create($this->userId, $book->getId(), 'Grandchild', $child->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->setIncluded($structure->getId(), $child->getId(), false);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertCount(1, $tree);
		$this->assertSame($root->getId(), $tree[0]['chapter']->getId());
		$this->assertCount(1, $tree[0]['children']);
		$this->assertSame($grandchild->getId(), $tree[0]['children'][0]['chapter']->getId());
		$this->assertSame('1.1', $tree[0]['children'][0]['number']);

		$entries = $this->entriesByChapter($structure->getId());
		$this->assertSame($root->getId(), $entries[$grandchild->getId()]['effectiveParentId']);
	}

	public function testExcludedRootChildrenKeepTheRootSlot(): void {
		$book = $this->books->create($this->userId, 'Promoted roots');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$childA = $this->chapters->create($this->userId, $book->getId(), 'Child A', $rootA->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->setIncluded($structure->getId(), $rootA->getId(), false);
		$this->setPosition($structure->getId(), $rootA->getId(), 2);
		$this->setPosition($structure->getId(), $rootB->getId(), 1);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertSame($rootB->getId(), $tree[0]['chapter']->getId());
		$this->assertSame($childA->getId(), $tree[1]['chapter']->getId());
		$this->assertSame('2.', $tree[1]['number']);

		$this->setPosition($structure->getId(), $rootA->getId(), 1);
		$this->setPosition($structure->getId(), $rootB->getId(), 2);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertSame($childA->getId(), $tree[0]['chapter']->getId());
		$this->assertSame('1.', $tree[0]['number']);
		$this->assertSame($rootB->getId(), $tree[1]['chapter']->getId());

		$entries = $this->entriesByChapter($structure->getId());
		$this->assertNull($entries[$childA->getId()]['effectiveParentId']);
	}

	public function testExcludedMiddleChapterChildrenKeepItsSlot(): void {
		$book = $this->books->create($this->userId, 'Promoted children');
		$root = $this->chapters->create($this->userId, $book->getId(), 'Root');
		$childA = $this->chapters->create($this->userId, $book->getId(), 'Child A', $root->getId());
		$childB = $this->chapters->create($this->userId, $book->getId(), 'Child B', $root->getId());
		$grandchild = $this->chapters->create($this->userId, $book->getId(), 'Grandchild', $childA->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->setIncluded($structure->getId(), $childA->getId(), false);
		$this->setPosition($structure->getId(), $childA->getId(), 2);
		$this->setPosition($structure->getId(), $childB->getId(), 1);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertSame($childB->getId(), $tree[0]['children'][0]['chapter']->getId());
		$this->assertSame($grandchild->getId(), $tree[0]['children'][1]['chapter']->getId());
		$this->assertSame('1.2', $tree[0]['children'][1]['number']);

		$this->setPosition($structure->getId(), $childA->getId(), 1);
		$this->setPosition($structure->getId(), $childB->getId(), 2);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());
		$this->assertSame($grandchild->getId(), $tree[0]['children'][0]['chapter']->getId());
		$this->assertSame('1.1', $tree[0]['children'][0]['number']);
		$this->assertSame($childB->getId(), $tree[0]['children'][1]['chapter']->getId());

		$entries = $this->entriesByChapter($structure->getId());
		$this->assertSame($root->getId(), $entries[$grandchild->getId()]['effectiveParentId']);
	}

	public function testSiblingPositionDrivesOrderAndNumbering(): void {
		$book = $this->books->create($this->userId, 'Ordering');
		$rootA = $this->chapters->create($this->userId, $book->getId(), 'Root A');
		$rootB = $this->chapters->create($this->userId, $book->getId(), 'Root B');
		$childA = $this->chapters->create($this->userId, $book->getId(), 'Child A', $rootA->getId());
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$this->setPosition($structure->getId(), $rootA->getId(), 2);
		$this->setPosition($structure->getId(), $rootB->getId(), 1);

		$tree = $this->chapters->tree($this->userId, $book->getId(), $structure->getId());

		$this->assertSame($rootB->getId(), $tree[0]['chapter']->getId());
		$this->assertSame('1.', $tree[0]['number']);
		$this->assertSame($rootA->getId(), $tree[1]['chapter']->getId());
		$this->assertSame('2.', $tree[1]['number']);
		$this->assertSame($childA->getId(), $tree[1]['children'][0]['chapter']->getId());
		$this->assertSame('2.1', $tree[1]['children'][0]['number']);
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

	private function setIncluded(int $structureId, int $chapterId, bool $included): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('booktailor_structure_chapters')
			->set('included', $qb->createNamedParameter($included, IQueryBuilder::PARAM_BOOL))
			->where($qb->expr()->eq('structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('chapter_id', $qb->createNamedParameter($chapterId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function setPosition(int $structureId, int $chapterId, int $position): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('booktailor_structure_chapters')
			->set('position', $qb->createNamedParameter($position, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('structure_id', $qb->createNamedParameter($structureId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('chapter_id', $qb->createNamedParameter($chapterId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}
}
