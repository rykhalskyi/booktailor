<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Chapter;

class StructureTreeBuilder {
	/**
	 * @param list<Chapter> $chapters
	 * @param list<array{chapter_id: int, included: bool, position: int}> $entries
	 * @return array{
	 *     tree: list<array{chapter: Chapter, children: array, effectiveParentId: ?int}>,
	 *     effectiveParentIds: array<int, ?int>
	 * }
	 */
	public function build(array $chapters, array $entries): array {
		$canonicalParent = [];
		foreach ($chapters as $chapter) {
			$canonicalParent[$chapter->getId()] = $chapter->getParentId();
		}

		$included = [];
		$positions = [];
		foreach ($entries as $entry) {
			if ($entry['included']) {
				$included[$entry['chapter_id']] = true;
			}
			$positions[$entry['chapter_id']] = $entry['position'];
		}

		$effectiveParentIds = [];
		$effectiveParent = function (int $chapterId) use (&$effectiveParent, &$effectiveParentIds, $canonicalParent, $included): ?int {
			if (array_key_exists($chapterId, $effectiveParentIds)) {
				return $effectiveParentIds[$chapterId];
			}

			$parentId = $canonicalParent[$chapterId] ?? null;
			while ($parentId !== null) {
				if (isset($included[$parentId])) {
					return $effectiveParentIds[$chapterId] = $parentId;
				}
				$parentId = $canonicalParent[$parentId] ?? null;
			}

			return $effectiveParentIds[$chapterId] = null;
		};

		foreach ($chapters as $chapter) {
			$effectiveParent($chapter->getId());
		}

		$childrenByParent = [];
		foreach ($chapters as $chapter) {
			$chapterId = $chapter->getId();
			if (!isset($included[$chapterId])) {
				continue;
			}

			$parentId = $effectiveParent($chapterId);
			$childrenByParent[$parentId ?? 0][] = $chapter;
		}

		foreach ($childrenByParent as &$siblings) {
			usort($siblings, static function (Chapter $a, Chapter $b) use ($positions): int {
				return ($positions[$a->getId()] ?? 0) <=> ($positions[$b->getId()] ?? 0)
					?: ($a->getId() <=> $b->getId());
			});
		}
		unset($siblings);

		$build = function (?int $parentId) use (&$build, $childrenByParent): array {
			/** @var list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $nodes */
			$nodes = [];
			foreach ($childrenByParent[$parentId ?? 0] ?? [] as $chapter) {
				/** @var list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $children */
				$children = $build($chapter->getId());
				$nodes[] = [
					'chapter' => $chapter,
					'children' => $children,
					'effectiveParentId' => $parentId,
				];
			}

			return $nodes;
		};

		return [
			'tree' => $build(null),
			'effectiveParentIds' => $effectiveParentIds,
		];
	}
}
