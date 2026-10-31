<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use DateTimeZone;
use OCA\BookTailor\BackgroundJob\ComposeJob;
use OCA\BookTailor\Db\AssetMapper;
use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Db\CompositionMapper;
use OCA\BookTailor\Db\StructureChapterMapper;
use OCA\BookTailor\Db\StructureMapper;
use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Entity\Composition;
use OCA\BookTailor\Entity\Structure;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCP\BackgroundJob\IJobList;
use OCP\Files\File;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class CompositionService {
	public const STALE_SECONDS = 1800;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly CompositionMapper $compositionMapper,
		private readonly BookMapper $bookMapper,
		private readonly StructureMapper $structureMapper,
		private readonly ChapterMapper $chapterMapper,
		private readonly StructureChapterMapper $structureChapterMapper,
		private readonly AssetMapper $assetMapper,
		private readonly StructureTreeBuilder $structureTreeBuilder,
		private readonly NumberingService $numberingService,
		private readonly MarkdownComposer $markdownComposer,
		private readonly StorageService $storage,
		private readonly BookService $bookService,
		private readonly IDBConnection $db,
		private readonly IJobList $jobList,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @return list<Composition>
	 */
	public function listForBook(string $userId, int $bookId, ?string $status = null, ?int $limit = null, int $offset = 0): array {
		$this->bookService->get($userId, $bookId);
		if ($status !== null && !in_array($status, self::statuses(), true)) {
			throw new ValidationException('Unknown composition status');
		}

		return $this->compositionMapper->findByBook($bookId, $status, $limit, $offset);
	}

	public function create(string $userId, int $bookId, int $structureId, ?string $name = null): Composition {
		$this->bookService->get($userId, $bookId);
		$structure = $this->getStructure($bookId, $structureId);

		$outputName = $this->normalizeOutputName($name);

		$this->db->beginTransaction();
		try {
			$now = new DateTime();
			$composition = new Composition();
			$composition->setBookId($bookId);
			$composition->setStructureId($structure->getId());
			$composition->setStructureName($structure->getName());
			$composition->setOutputName($outputName);
			$composition->setStatus(Composition::STATUS_QUEUED);
			$composition->setCreatedAt($now);
			$composition = $this->compositionMapper->insert($composition);

			$this->jobList->add(ComposeJob::class, ['compositionId' => $composition->getId()]);

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return $composition;
	}

	public function getOwned(string $userId, int $compositionId): Composition {
		$composition = $this->compositionMapper->findById($compositionId);
		if ($composition === null) {
			throw new NotFoundException('Composition not found');
		}

		$this->bookService->get($userId, $composition->getBookId());

		return $composition;
	}

	/**
	 * @return list<array{chapter: Chapter, number: string, assets: list<Asset>}>
	 */
	public function preview(string $userId, int $bookId, int $structureId): array {
		$book = $this->bookService->get($userId, $bookId);
		$structure = $this->getStructure($bookId, $structureId);
		$numbered = $this->numberedTree($book, $structure);
		$selectedByChapter = $this->includedAssetsByChapter($bookId);

		$groups = [];
		$this->collectPreviewGroups($numbered, $selectedByChapter, $groups);

		return $groups;
	}

	public function retry(string $userId, int $compositionId): Composition {
		$composition = $this->getOwned($userId, $compositionId);
		$stale = $this->isStale($composition);
		if ($composition->getStatus() !== Composition::STATUS_FAILED && !$stale) {
			throw new ConflictException('Only failed or stale compositions can be retried');
		}

		$this->compositionMapper->resetForRetry($compositionId);
		$this->jobList->add(ComposeJob::class, ['compositionId' => $compositionId]);

		$composition = $this->compositionMapper->findById($compositionId);
		if ($composition === null) {
			throw new NotFoundException('Composition not found');
		}

		return $composition;
	}

	public function delete(string $userId, int $compositionId): void {
		$composition = $this->getOwned($userId, $compositionId);
		if ($composition->getStatus() === Composition::STATUS_QUEUED
			|| $composition->getStatus() === Composition::STATUS_RUNNING) {
			throw new ConflictException('A composition that is still running cannot be deleted');
		}

		$fileId = $composition->getOutputFileId();
		if ($fileId !== null) {
			$this->storage->deleteNodeIfExists(
				$this->storage->findNodeById($userId, $fileId, File::class),
			);
		}

		$this->compositionMapper->delete($composition);
	}

	public function download(string $userId, int $compositionId): File {
		$composition = $this->getOwned($userId, $compositionId);
		if ($composition->getStatus() !== Composition::STATUS_SUCCEEDED) {
			throw new ConflictException('The composition output is not ready');
		}

		$fileId = $composition->getOutputFileId();
		if ($fileId === null) {
			throw new NotFoundException('Composition output not found');
		}

		$file = $this->storage->findNodeById($userId, $fileId, File::class);
		if (!$file instanceof File) {
			throw new NotFoundException('Composition output not found');
		}

		return $file;
	}

	public function isStale(Composition $composition): bool {
		if ($composition->getStatus() !== Composition::STATUS_RUNNING) {
			return false;
		}

		$startedAt = $composition->getStartedAt();
		if ($startedAt === null) {
			return false;
		}

		return time() - $startedAt->getTimestamp() > self::STALE_SECONDS;
	}

	public function run(int $compositionId): void {
		$composition = $this->compositionMapper->findById($compositionId);
		if ($composition === null || $composition->getStatus() !== Composition::STATUS_QUEUED) {
			return;
		}

		if (!$this->compositionMapper->claim($compositionId, new DateTime())) {
			return;
		}

		$started = microtime(true);
		try {
			$book = $this->bookMapper->findById($composition->getBookId());
			if ($book === null) {
				$this->fail($compositionId, 'The book was deleted before the composition ran');

				return;
			}

			$structureId = $composition->getStructureId();
			$structure = $structureId !== null ? $this->structureMapper->findById($structureId) : null;
			if ($structure === null || $structure->getBookId() !== $book->getId()) {
				$this->fail($compositionId, 'The structure was deleted before the composition ran');

				return;
			}

			$markdown = $this->buildOutput($book, $structure);
			$output = $this->storage->writeComposition(
				$book,
				$this->outputBaseName($composition),
				$markdown,
			);

			$this->compositionMapper->markSucceeded(
				$compositionId,
				$output['file']->getId(),
				$output['path'],
				new DateTime(),
			);
		} catch (\Throwable $e) {
			$message = $e instanceof ValidationException
				|| $e instanceof NotFoundException
				|| $e instanceof ConflictException
				? $e->getMessage()
				: 'The composition failed. Please retry.';

			$this->compositionMapper->markFailed($compositionId, $message, new DateTime());
			$this->logger->error('BookTailor composition failed', [
				'compositionId' => $compositionId,
				'bookId' => $composition->getBookId(),
				'duration' => round(microtime(true) - $started, 3),
				'exception' => $e,
			]);
		}
	}

	/**
	 * @return list<string>
	 */
	private static function statuses(): array {
		return [
			Composition::STATUS_QUEUED,
			Composition::STATUS_RUNNING,
			Composition::STATUS_SUCCEEDED,
			Composition::STATUS_FAILED,
		];
	}

	private function buildOutput(Book $book, Structure $structure): string {
		$numbered = $this->numberedTree($book, $structure);
		$selectedByChapter = $this->includedAssetsByChapter($book->getId());

		return $this->markdownComposer->compose($book, $numbered, $selectedByChapter)['markdown'];
	}

	private function getStructure(int $bookId, int $structureId): Structure {
		$structure = $this->structureMapper->findById($structureId);
		if ($structure === null || $structure->getBookId() !== $bookId) {
			throw new NotFoundException('Structure not found');
		}

		return $structure;
	}

	/**
	 * @return list<array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string}>
	 */
	private function numberedTree(Book $book, Structure $structure): array {
		$chapters = $this->chapterMapper->findByBook($book->getId());
		$entries = $this->structureChapterMapper->findByStructure($structure->getId());
		$tree = $this->structureTreeBuilder->build($chapters, $entries)['tree'];

		return $this->numberingService->number($tree);
	}

	/**
	 * @return array<int, list<Asset>>
	 */
	private function includedAssetsByChapter(int $bookId): array {
		$grouped = [];
		foreach ($this->assetMapper->findByBook($bookId) as $asset) {
			if ($asset->getIncludeInComposition()) {
				$grouped[$asset->getChapterId()][] = $asset;
			}
		}

		foreach ($grouped as &$assets) {
			usort($assets, static function (Asset $a, Asset $b): int {
				return strcasecmp($a->getName(), $b->getName())
					?: ($a->getId() <=> $b->getId());
			});
		}
		unset($assets);

		return $grouped;
	}

	/**
	 * @param array<array-key, mixed> $nodes
	 * @param array<int, list<Asset>> $selectedByChapter
	 * @param list<array{chapter: Chapter, number: string, assets: list<Asset>}> $groups
	 */
	private function collectPreviewGroups(array $nodes, array $selectedByChapter, array &$groups): void {
		foreach ($nodes as $node) {
			/** @var array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string} $node */
			$assets = $selectedByChapter[$node['chapter']->getId()] ?? [];
			if ($assets !== []) {
				$groups[] = [
					'chapter' => $node['chapter'],
					'number' => $node['number'],
					'assets' => $assets,
				];
			}

			$this->collectPreviewGroups($node['children'], $selectedByChapter, $groups);
		}
	}

	private function normalizeOutputName(?string $name): ?string {
		if ($name === null) {
			return null;
		}
		$name = trim($name);
		if ($name === '') {
			return null;
		}
		if (mb_strlen($name) > 255) {
			throw new ValidationException('Composition name is too long');
		}

		return $name;
	}

	private function outputBaseName(Composition $composition): string {
		$source = $composition->getOutputName() ?? $composition->getStructureName();
		$slug = mb_strtolower(trim($source));
		$slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug) ?? '';
		$slug = trim($slug, '-');
		if ($slug === '') {
			$slug = 'composition';
		}
		$slug = mb_substr($slug, 0, 80);
		$timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd-His');

		return $slug . '-' . $timestamp;
	}

	private function fail(int $compositionId, string $message): void {
		$this->compositionMapper->markFailed($compositionId, $message, new DateTime());
	}
}
