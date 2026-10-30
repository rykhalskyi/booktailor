<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service\Ai;

use DateTime;
use OCA\BookTailor\Db\AiActionMapper;
use OCA\BookTailor\Entity\AiAction;
use OCA\BookTailor\Exception\ApiException;
use OCA\BookTailor\Exception\ConflictException;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;
use OCA\BookTailor\Service\AssetService;
use OCA\BookTailor\Service\BookService;
use OCA\BookTailor\Service\ChapterService;
use OCA\BookTailor\Service\StructureService;
use OCA\BookTailor\Service\TagService;
use Psr\Log\LoggerInterface;

class AiProposalService {
	public const ACTION_CREATE_ASSET = 'create_asset';
	public const ACTION_UPDATE_ASSET = 'update_asset';
	public const ACTION_DELETE_ASSET = 'delete_asset';
	public const ACTION_CREATE_CHAPTER = 'create_chapter';
	public const ACTION_RENAME_CHAPTER = 'rename_chapter';
	public const ACTION_DELETE_CHAPTER = 'delete_chapter';
	public const ACTION_CREATE_STRUCTURE = 'create_structure';
	public const ACTION_RENAME_STRUCTURE = 'rename_structure';
	public const ACTION_DELETE_STRUCTURE = 'delete_structure';

	private const ACTIONS = [
		self::ACTION_CREATE_ASSET,
		self::ACTION_UPDATE_ASSET,
		self::ACTION_DELETE_ASSET,
		self::ACTION_CREATE_CHAPTER,
		self::ACTION_RENAME_CHAPTER,
		self::ACTION_DELETE_CHAPTER,
		self::ACTION_CREATE_STRUCTURE,
		self::ACTION_RENAME_STRUCTURE,
		self::ACTION_DELETE_STRUCTURE,
	];

	private const MAX_CONTENT_CHARS = 200000;

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly AiActionMapper $actionMapper,
		private readonly BookService $bookService,
		private readonly ChapterService $chapterService,
		private readonly AssetService $assetService,
		private readonly StructureService $structureService,
		private readonly TagService $tagService,
		private readonly LoggerInterface $logger,
	) {
	}

	public function describeActions(): string {
		return implode(', ', self::ACTIONS);
	}

	/**
	 * Validate and persist a proposal. Nothing is applied here.
	 *
	 * @param array<array-key, mixed> $payload
	 */
	public function propose(string $userId, int $bookId, string $action, array $payload): AiAction {
		$normalized = $this->validate($userId, $bookId, $action, $payload);
		$this->actionMapper->deleteOlderThanForUser($userId, new DateTime('-1 day'));

		$proposal = new AiAction();
		$proposal->setUserId($userId);
		$proposal->setBookId($bookId);
		$proposal->setAction($action);
		$proposal->setPayload((string)json_encode($normalized, JSON_UNESCAPED_SLASHES));
		$proposal->setStatus(AiAction::STATUS_PROPOSED);
		$proposal->setCreatedAt(new DateTime());

		return $this->actionMapper->insert($proposal);
	}

	/**
	 * Apply a pending proposal and discard it. Only the response keeps the decision.
	 *
	 * @return array{id: int, userId: string, bookId: ?int, action: string, payload: array<string, mixed>, status: string, createdAt: string}
	 */
	public function confirm(string $userId, int $actionId): array {
		$action = $this->getOwned($userId, $actionId);
		if ($action->getStatus() !== AiAction::STATUS_PROPOSED) {
			throw new ConflictException('Only a proposed AI action can be confirmed');
		}

		$bookId = (int)$action->getBookId();
		$payload = $this->decodePayload($action);

		try {
			$normalized = $this->validate($userId, $bookId, $action->getAction(), $payload);
			$this->apply($userId, $action->getAction(), $normalized);
		} catch (ApiException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->logger->error('BookTailor AI action failed', [
				'actionId' => $actionId,
				'action' => $action->getAction(),
				'exceptionClass' => $e::class,
			]);
			throw new ApiException('The AI action could not be applied', 422);
		}

		$action->setStatus(AiAction::STATUS_APPLIED);
		$serialized = $this->serialize($action);
		$this->actionMapper->delete($action);

		return $serialized;
	}

	/**
	 * Discard a pending proposal without writing anything.
	 *
	 * @return array{id: int, userId: string, bookId: ?int, action: string, payload: array<string, mixed>, status: string, createdAt: string}
	 */
	public function reject(string $userId, int $actionId): array {
		$action = $this->getOwned($userId, $actionId);
		if ($action->getStatus() !== AiAction::STATUS_PROPOSED) {
			throw new ConflictException('Only a proposed AI action can be rejected');
		}

		$action->setStatus(AiAction::STATUS_REJECTED);
		$serialized = $this->serialize($action);
		$this->actionMapper->delete($action);

		return $serialized;
	}

	public function getOwned(string $userId, int $actionId): AiAction {
		$action = $this->actionMapper->findByIdAndUser($actionId, $userId);
		if ($action === null) {
			throw new NotFoundException('AI action not found');
		}
		if ($action->getBookId() !== null) {
			$this->bookService->get($userId, (int)$action->getBookId());
		}

		return $action;
	}

	/**
	 * @return array{id: int, userId: string, bookId: ?int, action: string, payload: array<string, mixed>, status: string, createdAt: string}
	 */
	public function serialize(AiAction $action): array {
		return [
			'id' => $action->getId(),
			'userId' => $action->getUserId(),
			'bookId' => $action->getBookId(),
			'action' => $action->getAction(),
			'payload' => $this->decodePayload($action),
			'status' => $action->getStatus(),
			'createdAt' => $action->getCreatedAt()->format(\DateTimeInterface::ATOM),
		];
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validate(string $userId, int $bookId, string $action, array $payload): array {
		$this->bookService->get($userId, $bookId);
		if (!in_array($action, self::ACTIONS, true)) {
			throw new ValidationException('Unsupported AI action');
		}

		return match ($action) {
			self::ACTION_CREATE_ASSET => $this->validateCreateAsset($userId, $bookId, $payload),
			self::ACTION_UPDATE_ASSET => $this->validateUpdateAsset($userId, $bookId, $payload),
			self::ACTION_DELETE_ASSET => $this->validateDeleteAsset($userId, $bookId, $payload),
			self::ACTION_CREATE_CHAPTER => $this->validateCreateChapter($userId, $bookId, $payload),
			self::ACTION_RENAME_CHAPTER => $this->validateRenameChapter($userId, $bookId, $payload),
			self::ACTION_DELETE_CHAPTER => $this->validateDeleteChapter($userId, $bookId, $payload),
			self::ACTION_CREATE_STRUCTURE => $this->validateCreateStructure($userId, $bookId, $payload),
			self::ACTION_RENAME_STRUCTURE => $this->validateRenameStructure($userId, $bookId, $payload),
			self::ACTION_DELETE_STRUCTURE => $this->validateDeleteStructure($userId, $bookId, $payload),
		};
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateCreateAsset(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['chapterId', 'name', 'content', 'tagIds', 'summary']);
		$chapterId = $this->positiveInt($payload['chapterId'] ?? null, 'chapterId');
		$chapter = $this->chapterService->get($userId, $chapterId);
		if ($chapter->getBookId() !== $bookId) {
			throw new NotFoundException('Chapter not found');
		}

		$result = [
			'chapterId' => $chapterId,
			'name' => $this->shortText($payload['name'] ?? null, 'name', 255),
			'content' => $this->content($payload['content'] ?? null),
		];
		$result['tagIds'] = $this->tagIds($userId, $bookId, $payload['tagIds'] ?? []);
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateUpdateAsset(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['assetId', 'name', 'content', 'summary']);
		$assetId = $this->positiveInt($payload['assetId'] ?? null, 'assetId');
		$context = $this->assetService->get($userId, $assetId);
		if ($context['bookId'] !== $bookId) {
			throw new NotFoundException('Asset not found');
		}

		$result = ['assetId' => $assetId];
		if (array_key_exists('name', $payload)) {
			$result['name'] = $this->shortText($payload['name'], 'name', 255);
		}
		if (array_key_exists('content', $payload)) {
			$result['content'] = $this->content($payload['content']);
		}
		if (!isset($result['name']) && !isset($result['content'])) {
			throw new ValidationException('An asset update needs a name or content');
		}
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateDeleteAsset(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['assetId', 'summary']);
		$assetId = $this->positiveInt($payload['assetId'] ?? null, 'assetId');
		$context = $this->assetService->get($userId, $assetId);
		if ($context['bookId'] !== $bookId) {
			throw new NotFoundException('Asset not found');
		}

		$result = ['assetId' => $assetId];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateCreateChapter(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['bookId', 'name', 'parentId', 'summary']);
		$this->assertBookIdMatches($payload, $bookId);
		$result = [
			'bookId' => $bookId,
			'name' => $this->shortText($payload['name'] ?? null, 'name', 255),
			'parentId' => $this->nullablePositiveInt($payload['parentId'] ?? null, 'parentId'),
		];
		if ($result['parentId'] !== null) {
			$parent = $this->chapterService->get($userId, $result['parentId']);
			if ($parent->getBookId() !== $bookId) {
				throw new NotFoundException('Parent chapter not found');
			}
		}
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateRenameChapter(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['chapterId', 'name', 'summary']);
		$chapterId = $this->positiveInt($payload['chapterId'] ?? null, 'chapterId');
		$chapter = $this->chapterService->get($userId, $chapterId);
		if ($chapter->getBookId() !== $bookId) {
			throw new NotFoundException('Chapter not found');
		}

		$result = [
			'chapterId' => $chapterId,
			'name' => $this->shortText($payload['name'] ?? null, 'name', 255),
		];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateDeleteChapter(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['chapterId', 'summary']);
		$chapterId = $this->positiveInt($payload['chapterId'] ?? null, 'chapterId');
		$chapter = $this->chapterService->get($userId, $chapterId);
		if ($chapter->getBookId() !== $bookId) {
			throw new NotFoundException('Chapter not found');
		}

		$result = ['chapterId' => $chapterId];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateCreateStructure(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['bookId', 'name', 'summary']);
		$this->assertBookIdMatches($payload, $bookId);
		$this->bookService->get($userId, $bookId);
		$result = [
			'bookId' => $bookId,
			'name' => $this->shortText($payload['name'] ?? null, 'name', 255),
		];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateRenameStructure(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['structureId', 'name', 'summary']);
		$structureId = $this->positiveInt($payload['structureId'] ?? null, 'structureId');
		$structure = $this->structureService->getOwned($userId, $structureId);
		if ($structure->getBookId() !== $bookId) {
			throw new NotFoundException('Structure not found');
		}

		$result = [
			'structureId' => $structureId,
			'name' => $this->shortText($payload['name'] ?? null, 'name', 255),
		];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function validateDeleteStructure(string $userId, int $bookId, array $payload): array {
		$this->assertAllowedKeys($payload, ['structureId', 'summary']);
		$structureId = $this->positiveInt($payload['structureId'] ?? null, 'structureId');
		$structure = $this->structureService->getOwned($userId, $structureId);
		if ($structure->getBookId() !== $bookId) {
			throw new NotFoundException('Structure not found');
		}
		if ($structure->getIsDefault()) {
			throw new ValidationException('The default structure cannot be deleted');
		}

		$result = ['structureId' => $structureId];
		$this->copySummary($payload, $result);

		return $result;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function apply(string $userId, string $action, array $payload): void {
		match ($action) {
			self::ACTION_CREATE_ASSET => $this->applyCreateAsset($userId, $payload),
			self::ACTION_UPDATE_ASSET => $this->applyUpdateAsset($userId, $payload),
			self::ACTION_DELETE_ASSET => $this->applyDeleteAsset($userId, $payload),
			self::ACTION_CREATE_CHAPTER => $this->applyCreateChapter($userId, $payload),
			self::ACTION_RENAME_CHAPTER => $this->applyRenameChapter($userId, $payload),
			self::ACTION_DELETE_CHAPTER => $this->applyDeleteChapter($userId, $payload),
			self::ACTION_CREATE_STRUCTURE => $this->applyCreateStructure($userId, $payload),
			self::ACTION_RENAME_STRUCTURE => $this->applyRenameStructure($userId, $payload),
			self::ACTION_DELETE_STRUCTURE => $this->applyDeleteStructure($userId, $payload),
		};
	}

	/**
	 * @param array<string, mixed> $payload
	 * @psalm-suppress MixedAssignment, MixedArgumentTypeCoercion
	 */
	private function applyCreateAsset(string $userId, array $payload): void {
		$created = $this->assetService->createWithContent(
			$userId,
			(int)$payload['chapterId'],
			(string)$payload['name'],
			(string)$payload['content'],
		);
		$assetId = $created['asset']->getId();
		$tagIds = $payload['tagIds'] ?? [];
		if (is_array($tagIds) && $tagIds !== []) {
			try {
				$this->tagService->replaceForAsset($userId, $assetId, $tagIds);
			} catch (\Throwable $e) {
				$this->assetService->delete($userId, $assetId);
				throw $e;
			}
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyUpdateAsset(string $userId, array $payload): void {
		$assetId = (int)$payload['assetId'];
		$oldContent = isset($payload['name']) && isset($payload['content'])
			? $this->assetService->getContent($userId, $assetId)
			: null;
		if (isset($payload['content'])) {
			$this->assetService->updateContent($userId, $assetId, (string)$payload['content']);
		}
		if (isset($payload['name'])) {
			try {
				$this->assetService->rename($userId, $assetId, (string)$payload['name']);
			} catch (\Throwable $e) {
				if ($oldContent !== null) {
					$this->assetService->updateContent($userId, $assetId, $oldContent);
				}
				throw $e;
			}
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyDeleteAsset(string $userId, array $payload): void {
		$this->assetService->delete($userId, (int)$payload['assetId']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyCreateChapter(string $userId, array $payload): void {
		$this->chapterService->create(
			$userId,
			(int)$payload['bookId'],
			(string)$payload['name'],
			$payload['parentId'] !== null ? (int)$payload['parentId'] : null,
		);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyRenameChapter(string $userId, array $payload): void {
		$this->chapterService->rename($userId, (int)$payload['chapterId'], (string)$payload['name']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyDeleteChapter(string $userId, array $payload): void {
		$this->chapterService->delete($userId, (int)$payload['chapterId']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyCreateStructure(string $userId, array $payload): void {
		$this->structureService->create($userId, (int)$payload['bookId'], (string)$payload['name']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyRenameStructure(string $userId, array $payload): void {
		$this->structureService->rename($userId, (int)$payload['structureId'], (string)$payload['name']);
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function applyDeleteStructure(string $userId, array $payload): void {
		$this->structureService->delete($userId, (int)$payload['structureId']);
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @param list<string> $allowed
	 */
	private function assertAllowedKeys(array $payload, array $allowed): void {
		foreach (array_keys($payload) as $key) {
			if (!in_array($key, $allowed, true)) {
				throw new ValidationException('The AI proposal contains an unsupported field: ' . $key);
			}
		}
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @param array<string, mixed> $result
	 */
	/**
	 * @param array<array-key, mixed> $payload
	 */
	private function assertBookIdMatches(array $payload, int $bookId): void {
		if (!isset($payload['bookId'])) {
			return;
		}
		if ((int)$payload['bookId'] !== $bookId) {
			throw new ValidationException('The AI proposal belongs to a different book');
		}
	}

	/**
	 * @param array<array-key, mixed> $payload
	 * @param array<string, mixed> $result
	 * @psalm-suppress MixedAssignment
	 */
	private function copySummary(array $payload, array &$result): void {
		$summary = $payload['summary'] ?? null;
		if (is_string($summary) && trim($summary) !== '') {
			$result['summary'] = mb_substr(trim($summary), 0, 500);
		}
	}

	private function positiveInt(mixed $value, string $field): int {
		$id = filter_var($value, FILTER_VALIDATE_INT);
		if ($id === false || $id <= 0) {
			throw new ValidationException('A valid ' . $field . ' is required');
		}

		return $id;
	}

	private function nullablePositiveInt(mixed $value, string $field): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		return $this->positiveInt($value, $field);
	}

	private function shortText(mixed $value, string $field, int $maxLength): string {
		if (!is_string($value)) {
			throw new ValidationException('A ' . $field . ' is required');
		}
		$value = trim($value);
		if ($value === '') {
			throw new ValidationException('A ' . $field . ' is required');
		}
		if (mb_strlen($value) > $maxLength) {
			throw new ValidationException('The ' . $field . ' is too long');
		}

		return $value;
	}

	private function content(mixed $value): string {
		if (!is_string($value)) {
			throw new ValidationException('Markdown content is required');
		}
		if (mb_strlen($value) > self::MAX_CONTENT_CHARS) {
			throw new ValidationException('The proposed Markdown content is too large');
		}

		return $value;
	}

	/**
	 * @param mixed $value
	 * @return list<int>
	 */
	/** @psalm-suppress MixedAssignment */
	private function tagIds(string $userId, int $bookId, mixed $value): array {
		if ($value === null) {
			return [];
		}
		if (!is_array($value)) {
			throw new ValidationException('tagIds must be a list');
		}

		$ids = [];
		foreach ($value as $tagId) {
			$id = filter_var($tagId, FILTER_VALIDATE_INT);
			if ($id === false || $id <= 0) {
				throw new ValidationException('Every tag id must be valid');
			}
			$ids[] = $id;
		}
		$ids = array_values(array_unique($ids));
		if ($ids === []) {
			return [];
		}

		$owned = [];
		foreach ($this->tagService->listForBook($userId, $bookId) as $tag) {
			$owned[$tag->getId()] = true;
		}
		foreach ($ids as $id) {
			if (!isset($owned[$id])) {
				throw new NotFoundException('Tag not found');
			}
		}

		return $ids;
	}

	/**
	 * @return array<string, mixed>
	 * @psalm-suppress MixedAssignment
	 */
	private function decodePayload(AiAction $action): array {
		$decoded = json_decode($action->getPayload(), true);
		if (!is_array($decoded)) {
			return [];
		}

		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

}
