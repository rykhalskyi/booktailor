<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\Ai\AiToolDispatcher;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class AiToolDispatcherTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private AiToolDispatcher $tools;
	private int $bookId;
	private int $chapterId;
	private int $assetId;
	private int $otherBookId;
	private int $otherAssetId;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_assets')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-ai-tool-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->assets = $container->get(AssetService::class);
		$this->tools = $container->get(AiToolDispatcher::class);

		$book = $this->books->create($this->userId, 'Tools');
		$this->bookId = $book->getId();
		$this->chapterId = $this->chapters->create($this->userId, $this->bookId, 'Chapter')->getId();
		$this->assetId = $this->assets->createWithContent($this->userId, $this->chapterId, 'Note', 'Hello tool')['asset']->getId();

		$other = $this->books->create($this->userId, 'Other');
		$this->otherBookId = $other->getId();
		$otherChapter = $this->chapters->create($this->userId, $this->otherBookId, 'Other chapter')->getId();
		$this->otherAssetId = $this->assets->createWithContent($this->userId, $otherChapter, 'Other note', 'Other')['asset']->getId();
	}

	protected function tearDown(): void {
		foreach ([$this->bookId, $this->otherBookId] as $bookId) {
			try {
				$this->books->delete($this->userId, $bookId);
			} catch (\Throwable) {
				// Best-effort cleanup.
			}
		}

		$this->user->delete();
	}

	public function testReadToolsReturnOwnedContent(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'get_asset_content', ['assetId' => $this->assetId]);

		$this->assertSame('Hello tool', $result['content']);
	}

	public function testCrossBookAssetReadIsRejected(): void {
		$this->expectException(NotFoundException::class);
		$this->tools->read($this->userId, $this->bookId, 'get_asset_content', ['assetId' => $this->otherAssetId]);
	}

	public function testUnknownToolIsRejected(): void {
		$this->expectException(ValidationException::class);
		$this->tools->read($this->userId, $this->bookId, 'delete_everything', []);
	}
}
