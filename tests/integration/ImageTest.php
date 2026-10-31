<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\CompositionService;
use OCA\BookTailor\Service\ImageService;
use OCA\BookTailor\Service\StructureService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class ImageTest extends TestCase {
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private ImageService $images;
	private CompositionService $compositions;
	private StructureService $structures;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_assets')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-image-' . bin2hex(random_bytes(4));
		$this->user = $server->get(IUserManager::class)->createUser(
			$this->userId,
			'BookTailor-Test-123!',
		);
		$server->get(IUserSession::class)->setUser($this->user);

		$container = (new Application())->getContainer();
		$this->books = $container->get(BookService::class);
		$this->chapters = $container->get(ChapterService::class);
		$this->assets = $container->get(AssetService::class);
		$this->images = $container->get(ImageService::class);
		$this->compositions = $container->get(CompositionService::class);
		$this->structures = $container->get(StructureService::class);
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

	public function testUploadListReplaceAndDelete(): void {
		[$book, $chapter] = $this->bookAndChapter();
		$content = $this->png();

		$uploaded = $this->images->upload($this->userId, $chapter->getId(), 'cover.png', $content);
		$this->assertSame($book->getId(), $uploaded['bookId']);
		$this->assertSame('cover.png', $uploaded['image']['name']);
		$this->assertSame('image/png', $uploaded['image']['mime']);
		$fileId = $uploaded['image']['fileId'];

		$list = $this->images->list($this->userId, $chapter->getId());
		$this->assertCount(1, $list['images']);
		$this->assertSame('cover.png', $list['images'][0]['name']);

		$replaced = $this->images->replace($this->userId, $chapter->getId(), $fileId, $content);
		$this->assertSame('cover.png', $replaced['image']['name']);
		$this->assertSame($fileId, $replaced['image']['fileId']);

		$this->images->delete($this->userId, $chapter->getId(), $fileId);
		$this->assertSame([], $this->images->list($this->userId, $chapter->getId())['images']);
	}

	public function testUploadValidatesTypeExtensionAndPaths(): void {
		[, $chapter] = $this->bookAndChapter();

		$this->expectException(ValidationException::class);
		$this->images->upload($this->userId, $chapter->getId(), 'fake.png', 'not an image');
	}

	public function testUploadRejectsSvg(): void {
		[, $chapter] = $this->bookAndChapter();

		$this->expectException(ValidationException::class);
		$this->images->upload(
			$this->userId,
			$chapter->getId(),
			'logo.svg',
			'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
		);
	}

	public function testUploadRejectsMismatchedExtension(): void {
		[, $chapter] = $this->bookAndChapter();

		$this->expectException(ValidationException::class);
		$this->images->upload($this->userId, $chapter->getId(), 'cover.jpg', $this->png());
	}

	public function testUploadRejectsPathSeparators(): void {
		[, $chapter] = $this->bookAndChapter();

		$this->expectException(ValidationException::class);
		$this->images->upload($this->userId, $chapter->getId(), '../../cover.png', $this->png());
	}

	public function testUploadRejectsOversizeImage(): void {
		[, $chapter] = $this->bookAndChapter();

		$this->expectException(ValidationException::class);
		$this->images->upload(
			$this->userId,
			$chapter->getId(),
			'large.png',
			str_repeat('a', \OCA\BookTailor\Service\ImageService::MAX_IMAGE_BYTES + 1),
		);
	}

	public function testDeleteIsBlockedWhileReferenced(): void {
		[, $chapter] = $this->bookAndChapter();
		$uploaded = $this->images->upload($this->userId, $chapter->getId(), 'cover.png', $this->png());
		$fileId = $uploaded['image']['fileId'];
		$asset = $this->asset($chapter, 'Chapter text', '![cover](images/cover.png)');

		try {
			$this->images->delete($this->userId, $chapter->getId(), $fileId);
			$this->fail('A referenced image must not be deleted');
		} catch (ConflictException $e) {
			$this->assertStringContainsString($asset->getFileName(), $e->getMessage());
		}

		$this->assets->updateContent($this->userId, $asset->getId(), 'No image reference');
		$this->images->delete($this->userId, $chapter->getId(), $fileId);
		$this->assertSame([], $this->images->list($this->userId, $chapter->getId())['images']);
	}

	public function testImagesAreOwnerScoped(): void {
		[, $chapter] = $this->bookAndChapter();
		$uploaded = $this->images->upload($this->userId, $chapter->getId(), 'cover.png', $this->png());
		$intruderId = 'bt-image-intruder-' . bin2hex(random_bytes(4));
		$intruder = \OC::$server->get(IUserManager::class)->createUser(
			$intruderId,
			'BookTailor-Test-123!',
		);

		try {
			$this->expectException(NotFoundException::class);
			$this->images->replace($intruderId, $chapter->getId(), $uploaded['image']['fileId'], $this->png());
		} finally {
			$intruder->delete();
		}
	}

	public function testUploadedImageSurvivesComposition(): void {
		[$book, $chapter] = $this->bookAndChapter();
		$uploaded = $this->images->upload($this->userId, $chapter->getId(), 'cover.png', $this->png());
		$asset = $this->asset($chapter, 'Illustrated', '![cover](images/cover.png)');
		$this->assets->setIncludeInComposition($this->userId, $asset->getId(), true);
		$structure = $this->structures->listForBook($this->userId, $book->getId())[0];

		$composition = $this->compositions->create(
			$this->userId,
			$book->getId(),
			$structure->getId(),
		);
		$compositionId = $composition->getId();
		$this->compositions->run($compositionId);

		$composition = $this->compositions->getOwned($this->userId, $compositionId);
		$this->assertSame(
			\OCA\BookTailor\Entity\Composition::STATUS_SUCCEEDED,
			$composition->getStatus(),
			(string)$composition->getError(),
		);
		$markdown = $this->compositions->download($this->userId, $compositionId)->getContent();
		$this->assertStringContainsString(
			'![cover](images/cover-' . $uploaded['image']['fileId'] . '.png)',
			$markdown,
		);
	}

	/**
	 * @return array{Book, Chapter}
	 */
	private function bookAndChapter(): array {
		$book = $this->books->create($this->userId, 'Images');
		$chapter = $this->chapters->create($this->userId, $book->getId(), 'Chapter');

		return [$book, $chapter];
	}

	private function asset(Chapter $chapter, string $name, string $content): Asset {
		$created = $this->assets->create($this->userId, $chapter->getId(), $name);
		$this->assets->updateContent($this->userId, $created['asset']->getId(), $content);

		return $created['asset'];
	}

	private function png(): string {
		return base64_decode(self::PNG, true) ?: '';
	}
}
