<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Entity\AiAction;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\Ai\AiProposalService;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class AiProposalTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private StructureService $structures;
	private AiProposalService $proposals;
	private int $bookId;
	private int $chapterId;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_ai_action_log')) {
			$this->markTestSkipped('BookTailor AI tables are not migrated');
		}

		$this->userId = 'bt-ai-prop-' . bin2hex(random_bytes(4));
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
		$this->proposals = $container->get(AiProposalService::class);

		$book = $this->books->create($this->userId, 'AI Proposals');
		$this->bookId = $book->getId();
		$this->chapterId = $this->chapters->create($this->userId, $this->bookId, 'Chapter')->getId();
	}

	protected function tearDown(): void {
		try {
			$this->books->delete($this->userId, $this->bookId);
		} catch (\Throwable) {
			// Best-effort cleanup.
		}

		$this->user->delete();
	}

	public function testCreateAssetProposalDoesNotWriteUntilConfirmed(): void {
		$proposal = $this->proposals->propose($this->userId, $this->bookId, 'create_asset', [
			'chapterId' => $this->chapterId,
			'name' => 'Summary',
			'content' => "# Summary\n\nGenerated text.\n",
			'summary' => 'Create a summary asset',
		]);

		$this->assertSame(AiAction::STATUS_PROPOSED, $proposal->getStatus());
		$this->assertCount(0, $this->assets->list($this->userId, $this->chapterId)['assets']);

		$applied = $this->proposals->confirm($this->userId, $proposal->getId());
		$this->assertSame(AiAction::STATUS_APPLIED, $applied['status']);

		$assets = $this->assets->list($this->userId, $this->chapterId)['assets'];
		$this->assertCount(1, $assets);
		$this->assertSame('Summary', $assets[0]->getName());
		$this->assertSame("# Summary\n\nGenerated text.\n", $this->assets->getContent($this->userId, $assets[0]->getId()));

		$this->expectException(NotFoundException::class);
		$this->proposals->confirm($this->userId, $proposal->getId());
	}

	public function testChapterProposalAppliesAfterConfirmation(): void {
		$proposal = $this->proposals->propose($this->userId, $this->bookId, 'create_chapter', [
			'name' => 'AI chapter',
			'parentId' => $this->chapterId,
		]);

		$applied = $this->proposals->confirm($this->userId, $proposal->getId());
		$this->assertSame(AiAction::STATUS_APPLIED, $applied['status']);

		$tree = $this->chapters->tree($this->userId, $this->bookId);
		$this->assertSame('AI chapter', $tree[0]['children'][0]['chapter']->getName());
	}

	public function testRejectedProposalDoesNotWrite(): void {
		$proposal = $this->proposals->propose($this->userId, $this->bookId, 'create_asset', [
			'chapterId' => $this->chapterId,
			'name' => 'Rejected',
			'content' => 'No write',
		]);

		$rejected = $this->proposals->reject($this->userId, $proposal->getId());
		$this->assertSame(AiAction::STATUS_REJECTED, $rejected['status']);
		$this->assertCount(0, $this->assets->list($this->userId, $this->chapterId)['assets']);

		$this->expectException(NotFoundException::class);
		$this->proposals->getOwned($this->userId, $proposal->getId());
	}

	public function testProposalValidationRejectsUnsafePayloads(): void {
		$this->expectException(ValidationException::class);
		$this->proposals->propose($this->userId, $this->bookId, 'create_asset', [
			'chapterId' => $this->chapterId,
			'name' => 'Bad',
			'content' => 'Bad',
			'unexpected' => true,
		]);
	}

	public function testDefaultStructureCannotBeDeletedByAi(): void {
		$default = $this->structures->listForBook($this->userId, $this->bookId)[0];

		$this->expectException(ValidationException::class);
		$this->proposals->propose($this->userId, $this->bookId, 'delete_structure', [
			'structureId' => $default->getId(),
		]);
	}

	public function testOwnershipIsEnforcedOnConfirm(): void {
		$proposal = $this->proposals->propose($this->userId, $this->bookId, 'create_asset', [
			'chapterId' => $this->chapterId,
			'name' => 'Private',
			'content' => 'Private',
		]);
		$other = \OC::$server->get(IUserManager::class)->createUser(
			'bt-ai-prop-other-' . bin2hex(random_bytes(4)),
			'BookTailor-Test-123!',
		);

		try {
			$this->expectException(NotFoundException::class);
			$this->proposals->confirm($other->getUID(), $proposal->getId());
		} finally {
			$other->delete();
		}
	}
}
