<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Chapter;
use OCA\BookTailor\Exception\NotFoundException;
use OCA\BookTailor\Exception\ValidationException;

class ChapterReferenceService {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly ChapterService $chapterService,
	) {
	}

	/**
	 * @return list<array{chapter: Chapter, number: string, path: string, children: list<array<array-key, mixed>>}>
	 * @psalm-suppress MixedAssignment, MixedArgument
	 */
	public function tree(string $userId, int $bookId, ?int $structureId = null): array {
		return $this->decorate($this->chapterService->tree($userId, $bookId, $structureId), '', '');
	}

	/**
	 * @param list<mixed> $nodes
	 * @return list<array{chapter: Chapter, number: string, path: string, children: list<array<array-key, mixed>>}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedArrayAccess, RedundantCondition
	 */
	private function decorate(array $nodes, string $parentPath, string $numberPrefix, int $depth = 0): array {
		$result = [];
		foreach ($nodes as $index => $node) {
			if (!is_array($node) || !isset($node['chapter']) || !$node['chapter'] instanceof Chapter) {
				continue;
			}

			$chapter = $node['chapter'];
			$number = $node['number'] ?? ($depth === 0 ? ($index + 1) . '.' : $numberPrefix . ($index + 1));
			$number = is_string($number) ? $number : (string)$number;
			$path = $parentPath === '' ? $chapter->getName() : $parentPath . ' / ' . $chapter->getName();
			$children = $node['children'] ?? [];
			$children = is_array($children) ? array_values($children) : [];

			$result[] = [
				'chapter' => $chapter,
				'number' => $number,
				'path' => $path,
				'children' => $this->decorate($children, $path, rtrim($number, '.') . '.', $depth + 1),
			];
		}

		return $result;
	}

	/**
	 * @param list<array{chapter: Chapter, number: string, path: string, children: list<array<array-key, mixed>>}> $nodes
	 * @return list<array{chapter: Chapter, number: string, path: string}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedArgumentTypeCoercion, MixedArrayAccess
	 */
	public function flatten(array $nodes): array {
		$result = [];
		foreach ($nodes as $node) {
			$result[] = [
				'chapter' => $node['chapter'],
				'number' => $node['number'],
				'path' => $node['path'],
			];
			$result = array_merge($result, $this->flatten($node['children']));
		}

		return $result;
	}

	/**
	 * @return list<array{chapter: Chapter, number: string, path: string}>
	 * @psalm-suppress MixedAssignment
	 */
	public function matches(string $userId, int $bookId, ?int $structureId, string $reference): array {
		$normalized = $this->normalize($reference);
		if ($normalized === '') {
			return [];
		}

		$matches = [];
		foreach ($this->flatten($this->tree($userId, $bookId, $structureId)) as $node) {
			$chapter = $node['chapter'];
			$number = mb_strtolower($node['number']);
			$name = mb_strtolower($chapter->getName());
			$path = mb_strtolower($node['path']);
			$numberAndName = mb_strtolower($node['number'] . ' ' . $chapter->getName());
			$numberAndPath = mb_strtolower($node['number'] . ' ' . $node['path']);
			$normalizedNumber = rtrim($number, '.');

			if ($normalized === $normalizedNumber
				|| $normalized === $number
				|| $normalized === $name
				|| $normalized === $path
				|| $normalized === $numberAndName
				|| $normalized === $numberAndPath) {
				$matches[$chapter->getId()] = $node;
			}
		}

		return array_values($matches);
	}

	/**
	 * @return array{chapter: Chapter, number: string, path: string}
	 */
	public function resolve(string $userId, int $bookId, ?int $structureId, string $reference): array {
		$matches = $this->matches($userId, $bookId, $structureId, $reference);
		if ($matches === []) {
			throw new NotFoundException('Chapter not found: ' . $reference);
		}
		if (count($matches) > 1) {
			throw new ValidationException(
				'Chapter reference is ambiguous: ' . $reference
				. '. Candidates: ' . $this->describe($matches),
			);
		}

		return $matches[0];
	}

	/**
	 * @param list<array{chapter: Chapter, number: string, path: string}> $nodes
	 */
	public function describe(array $nodes): string {
		$parts = [];
		foreach ($nodes as $node) {
			$parts[] = $node['number'] . ' "' . $node['path'] . '"';
		}

		return implode(', ', $parts);
	}

	/**
	 * @return array{chapter: Chapter, number: string, path: string}
	 */
	public function findByChapterId(string $userId, int $bookId, ?int $structureId, int $chapterId): array {
		foreach ($this->flatten($this->tree($userId, $bookId, $structureId)) as $node) {
			if ($node['chapter']->getId() === $chapterId) {
				return $node;
			}
		}

		throw new NotFoundException('Chapter not found in the active structure: ' . $chapterId);
	}

	/**
	 * @return list<array{chapter: Chapter, number: string, path: string}>
	 */
	public function withDescendants(string $userId, int $bookId, ?int $structureId, int $chapterId): array {
		return $this->subtree($this->tree($userId, $bookId, $structureId), $chapterId);
	}

	/**
	 * @param list<array{chapter: Chapter, number: string, path: string, children: list<array<array-key, mixed>>}> $nodes
	 * @return list<array{chapter: Chapter, number: string, path: string}>
	 * @psalm-suppress MixedAssignment, MixedArgument, MixedArgumentTypeCoercion, MixedArrayAccess
	 */
	private function subtree(array $nodes, int $chapterId): array {
		foreach ($nodes as $node) {
			if ($node['chapter']->getId() === $chapterId) {
				return $this->flatten([$node]);
			}
			$found = $this->subtree($node['children'], $chapterId);
			if ($found !== []) {
				return $found;
			}
		}

		return [];
	}

	private function normalize(string $reference): string {
		$reference = trim(mb_strtolower($reference));
		$reference = (string)preg_replace('/\s+/u', ' ', $reference);
		$reference = (string)preg_replace('/^(chapter|ch\.?|#)\s*/iu', '', $reference);

		return trim($reference);
	}
}
