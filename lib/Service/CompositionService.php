<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use DateTime;
use DateTimeZone;
use OCA\BookTailor\BackgroundJob\ComposeJob;
use OCA\BookTailor\Db\AssetMapper;
use OCA\BookTailor\Db\AssetTagMapper;
use OCA\BookTailor\Db\BookMapper;
use OCA\BookTailor\Db\ChapterMapper;
use OCA\BookTailor\Db\CompositionMapper;
use OCA\BookTailor\Db\CompositionRuleMapper;
use OCA\BookTailor\Db\StructureChapterMapper;
use OCA\BookTailor\Db\StructureMapper;
use OCA\BookTailor\Db\TagMapper;
use OCA\BookTailor\Entity\Book;
use OCA\BookTailor\Entity\Composition;
use OCA\BookTailor\Entity\CompositionRule;
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
		private readonly CompositionRuleMapper $ruleMapper,
		private readonly BookMapper $bookMapper,
		private readonly StructureMapper $structureMapper,
		private readonly ChapterMapper $chapterMapper,
		private readonly StructureChapterMapper $structureChapterMapper,
		private readonly AssetMapper $assetMapper,
		private readonly AssetTagMapper $assetTagMapper,
		private readonly TagMapper $tagMapper,
		private readonly StructureTreeBuilder $structureTreeBuilder,
		private readonly NumberingService $numberingService,
		private readonly CompositionRuleResolver $ruleResolver,
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

	/**
	 * @param array<array-key, mixed> $rules
	 * @return array{composition: Composition, rules: list<CompositionRule>}
	 */
	public function create(string $userId, int $bookId, int $structureId, array $rules, ?string $name = null): array {
		$this->bookService->get($userId, $bookId);
		$structure = $this->structureMapper->findById($structureId);
		if ($structure === null || $structure->getBookId() !== $bookId) {
			throw new NotFoundException('Structure not found');
		}

		$normalizedRules = $this->normalizeRules($rules, $bookId);
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

			$this->ruleMapper->replaceForComposition($composition->getId(), $normalizedRules);
			$this->jobList->add(ComposeJob::class, ['compositionId' => $composition->getId()]);

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return [
			'composition' => $composition,
			'rules' => $this->ruleMapper->findByComposition($composition->getId()),
		];
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
	 * @return list<CompositionRule>
	 */
	public function rulesFor(int $compositionId): array {
		return $this->ruleMapper->findByComposition($compositionId);
	}

	/**
	 * @param list<int> $compositionIds
	 * @return array<int, list<CompositionRule>>
	 */
	public function rulesForMany(array $compositionIds): array {
		return $this->ruleMapper->findByCompositionIds($compositionIds);
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

			$markdown = $this->buildOutput($book, $structure, $composition);
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

	private function buildOutput(Book $book, Structure $structure, Composition $composition): string {
		$chapters = $this->chapterMapper->findByBook($book->getId());
		$entries = $this->structureChapterMapper->findByStructure($structure->getId());
		$tree = $this->structureTreeBuilder->build($chapters, $entries)['tree'];
		$numbered = $this->numberingService->number($tree);

		$assetsByChapter = [];
		$assetIds = [];
		foreach ($this->assetMapper->findByBook($book->getId()) as $asset) {
			$assetsByChapter[$asset->getChapterId()][] = $asset;
			$assetIds[] = $asset->getId();
		}

		$tagIdsByAsset = $this->assetTagMapper->findTagIdsByAssetIds($assetIds);
		$rules = [];
		foreach ($this->ruleMapper->findByComposition($composition->getId()) as $rule) {
			$rules[] = [
				'tagId' => $rule->getTagId(),
				'priority' => $rule->getPriority(),
				'fallback' => $rule->getFallback(),
			];
		}

		$selectedByChapter = [];
		foreach ($chapters as $chapter) {
			$selectedByChapter[$chapter->getId()] = $this->ruleResolver->select(
				$assetsByChapter[$chapter->getId()] ?? [],
				$tagIdsByAsset,
				$rules,
			);
		}

		return $this->markdownComposer->compose($book, $numbered, $selectedByChapter)['markdown'];
	}

	/**
	 * @param array<array-key, mixed> $rules
	 * @return list<array{tagId: int, priority: int, fallback: bool}>
	 */
	private function normalizeRules(array $rules, int $bookId): array {
		$normalized = [];
		$seenTags = [];
		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				throw new ValidationException('Composition rules must be objects');
			}

			$tagId = filter_var($rule['tagId'] ?? null, FILTER_VALIDATE_INT);
			if ($tagId === false || $tagId <= 0) {
				throw new ValidationException('A valid tag id is required for every rule');
			}
			if (isset($seenTags[$tagId])) {
				throw new ValidationException('Each tag may appear only once in the rules');
			}
			$seenTags[$tagId] = true;

			$priority = filter_var($rule['priority'] ?? null, FILTER_VALIDATE_INT);
			if ($priority === false || $priority < 0) {
				throw new ValidationException('A valid rule priority is required');
			}

			$fallback = $rule['fallback'] ?? false;
			if (!is_bool($fallback)) {
				throw new ValidationException('The fallback flag must be a boolean');
			}

			$normalized[] = [
				'tagId' => $tagId,
				'priority' => $priority,
				'fallback' => $fallback,
			];
		}

		if ($normalized !== []) {
			$tagIds = array_map(static fn (array $rule): int => $rule['tagId'], $normalized);
			$tags = $this->tagMapper->findByIdsAndBook($tagIds, $bookId);
			if (count($tags) !== count($tagIds)) {
				throw new ValidationException('Every rule tag must belong to this book');
			}
		}

		usort($normalized, static function (array $a, array $b): int {
			return $a['priority'] <=> $b['priority']
				?: $a['tagId'] <=> $b['tagId'];
		});
		foreach ($normalized as $index => &$rule) {
			$rule['priority'] = $index + 1;
		}
		unset($rule);

		return $normalized;
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
