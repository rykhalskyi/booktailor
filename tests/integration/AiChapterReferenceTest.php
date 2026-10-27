<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Integration;

use OCA\BookTailor\AppInfo\Application;
use OCA\BookTailor\Service\Ai\AiContextService;
use OCA\BookTailor\Service\Ai\AiToolDispatcher;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCA\BookTailor\Service\TagService;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

final class AiChapterReferenceTest extends TestCase {
	private string $userId;
	private IUser $user;
	private BookService $books;
	private ChapterService $chapters;
	private AssetService $assets;
	private TagService $tags;
	private StructureService $structures;
	private AiToolDispatcher $tools;
	private AiContextService $context;
	private int $bookId;
	private int $structureId;
	private int $partId;
	private int $fractionsId;
	private int $decimalsId;
	private int $detailsId;
	private int $otherId;
	private int $termTagId;
	private int $definitionTagId;

	protected function setUp(): void {
		$server = \OC::$server;
		$db = $server->get(IDBConnection::class);
		if (!$db->tableExists('booktailor_assets')) {
			$this->markTestSkipped('BookTailor tables are not migrated');
		}

		$this->userId = 'bt-ai-ref-' . bin2hex(random_bytes(4));
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
		$this->tools = $container->get(AiToolDispatcher::class);
		$this->context = $container->get(AiContextService::class);

		$book = $this->books->create($this->userId, 'References');
		$this->bookId = $book->getId();
		$this->structureId = $this->structures->listForBook($this->userId, $this->bookId)[0]->getId();

		$this->partId = $this->chapters->create($this->userId, $this->bookId, 'Part')->getId();
		$this->fractionsId = $this->chapters->create($this->userId, $this->bookId, 'Fractions', $this->partId)->getId();
		$this->decimalsId = $this->chapters->create($this->userId, $this->bookId, 'Decimals', $this->partId)->getId();
		$this->detailsId = $this->chapters->create($this->userId, $this->bookId, 'Fraction details', $this->fractionsId)->getId();
		$this->otherId = $this->chapters->create($this->userId, $this->bookId, 'Other')->getId();

		$this->termTagId = $this->tags->create($this->userId, $this->bookId, 'term')->getId();
		$this->definitionTagId = $this->tags->create($this->userId, $this->bookId, 'definition')->getId();

		$this->createAsset($this->fractionsId, 'Fractions terms', 'A fraction is a ratio.', [$this->termTagId]);
		$this->createAsset($this->decimalsId, 'Decimals terms', 'A decimal is a base-ten fraction.', [$this->termTagId]);
		$this->createAsset($this->detailsId, 'Details terms', 'A denominator is the bottom number.', [$this->termTagId]);
		$this->createAsset($this->otherId, 'Other terms', 'Other term.', [$this->termTagId]);
		$this->createAsset($this->fractionsId, 'Not terms', 'Not a term asset.', [$this->definitionTagId]);
	}

	protected function tearDown(): void {
		try {
			$this->books->delete($this->userId, $this->bookId);
		} catch (\Throwable) {
			// Best-effort cleanup.
		}

		$this->user->delete();
	}

	public function testChapterTreeExposesNumbersAndPaths(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'list_chapters', [], $this->structureId);

		$this->assertSame('1.', $result['chapters'][0]['number']);
		$this->assertSame('Part', $result['chapters'][0]['path']);
		$this->assertSame('1.1', $result['chapters'][0]['children'][0]['number']);
		$this->assertSame('Part / Fractions', $result['chapters'][0]['children'][0]['path']);
		$this->assertSame('1.1.1', $result['chapters'][0]['children'][0]['children'][0]['number']);
	}

	public function testContextIncludesChapterNumbersPathsAndAssetTags(): void {
		$context = $this->context->build($this->userId, $this->bookId, $this->structureId, $this->fractionsId);
		$text = $context['text'];

		$this->assertStringContainsString('1.1 "Part / Fractions"', $text);
		$this->assertStringContainsString('tags=[term]', $text);
		$this->assertStringContainsString('A fraction is a ratio.', $text);
	}

	public function testGetChapterResolvesNumberNameAndPath(): void {
		foreach (['1.1', 'Fractions', 'Part / Fractions', 'chapter 1.1', '1.1 Fractions'] as $reference) {
			$result = $this->tools->read($this->userId, $this->bookId, 'get_chapter', ['chapter' => $reference], $this->structureId);
			$this->assertSame($this->fractionsId, $result['id'], $reference);
		}
	}

	public function testCollectAssetsFiltersByChapterNumberAndTag(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterNumbers' => ['1.1', '1.2'],
			'tagNames' => ['term'],
		], $this->structureId);

		$this->assertCount(2, $result['assets']);
		$this->assertSame([$this->fractionsId, $this->decimalsId], array_map(
			static fn (array $asset): int => (int)$asset['chapterId'],
			$result['assets'],
		));
		$this->assertSame('A fraction is a ratio.', $result['assets'][0]['content']);
		$this->assertSame('term', $result['assets'][0]['tags'][0]['name']);
	}

	public function testCollectAssetsExcludesDescendantsByDefault(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterNumbers' => ['1.1'],
			'tagNames' => ['term'],
		], $this->structureId);

		$this->assertCount(1, $result['assets']);
		$this->assertSame($this->fractionsId, $result['assets'][0]['chapterId']);
	}

	public function testCollectAssetsCanIncludeDescendantsWhenAsked(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterNames' => ['Fractions'],
			'tagNames' => ['term'],
			'includeDescendants' => true,
		], $this->structureId);

		$this->assertCount(2, $result['assets']);
		$this->assertSame(['1.1', '1.1.1'], array_map(
			static fn (array $asset): string => (string)$asset['chapterNumber'],
			$result['assets'],
		));
	}

	public function testCollectAssetsFiltersByAnyOrAllTags(): void {
		$any = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterIds' => [$this->fractionsId],
			'tagNames' => ['term', 'definition'],
			'tagMode' => 'any',
		], $this->structureId);
		$this->assertCount(2, $any['assets']);

		$this->tags->replaceForAsset($this->userId, $any['assets'][0]['id'], [$this->termTagId, $this->definitionTagId]);
		$all = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterIds' => [$this->fractionsId],
			'tagNames' => ['term', 'definition'],
			'tagMode' => 'all',
		], $this->structureId);
		$this->assertCount(1, $all['assets']);
	}

	public function testUnknownTagReturnsAvailableTagsAndNoAssets(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterNumbers' => ['1.1'],
			'tagNames' => ['missing'],
		], $this->structureId);

		$this->assertCount(0, $result['assets']);
		$this->assertSame(['missing'], $result['issues']['unknownTags']);
		$this->assertContains('term', array_column($result['availableTags'], 'name'));
	}

	public function testAmbiguousChapterNameReturnsCandidates(): void {
		$this->chapters->create($this->userId, $this->bookId, 'Fractions', $this->otherId);

		$result = $this->tools->read($this->userId, $this->bookId, 'collect_assets', [
			'chapterNames' => ['Fractions'],
			'tagNames' => ['term'],
		], $this->structureId);

		$this->assertCount(0, $result['assets']);
		$this->assertCount(1, $result['issues']['ambiguous']);
		$this->assertCount(2, $result['issues']['ambiguous'][0]['candidates']);
	}

	public function testListAssetsIncludesTagsAndChapterReference(): void {
		$result = $this->tools->read($this->userId, $this->bookId, 'list_assets', [
			'chapter' => '1.1',
		], $this->structureId);

		$this->assertSame('1.1', $result['chapterNumber']);
		$this->assertSame('Part / Fractions', $result['chapterPath']);
		$this->assertSame('term', $result['assets'][0]['tags'][0]['name']);
	}

	/**
	 * @param list<int> $tagIds
	 */
	private function createAsset(int $chapterId, string $name, string $content, array $tagIds): void {
		$assetId = $this->assets->createWithContent($this->userId, $chapterId, $name, $content)['asset']->getId();
		$this->tags->replaceForAsset($this->userId, $assetId, $tagIds);
	}
}
