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
		$included = [];
		$positions = [];
		foreach ($entries as $entry) {
			if ($entry['included']) {
				$included[$entry['chapter_id']] = true;
			}
			$positions[$entry['chapter_id']] = $entry['position'];
		}

		$childrenByCanonicalParent = [];
		foreach ($chapters as $chapter) {
			$childrenByCanonicalParent[$chapter->getParentId() ?? 0][] = $chapter;
		}
		foreach ($childrenByCanonicalParent as &$siblings) {
			usort($siblings, static function (Chapter $a, Chapter $b) use ($positions): int {
				return ($positions[$a->getId()] ?? 0) <=> ($positions[$b->getId()] ?? 0)
					?: ($a->getId() <=> $b->getId());
			});
		}
		unset($siblings);

		/** @var array<int, ?int> $effectiveParentIds */
		$effectiveParentIds = [];

		/**
		 * Excluded chapters are not emitted, but their children are promoted into the
		 * excluded chapter's slot under the nearest included ancestor.
		 *
		 * @return list<array{chapter: Chapter, children: array, effectiveParentId: ?int}>
		 */
		$build = function (?int $effectiveParentId, int $canonicalParentKey) use (&$build, $childrenByCanonicalParent, $included, &$effectiveParentIds): array {
			/** @var list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $nodes */
			$nodes = [];
			foreach ($childrenByCanonicalParent[$canonicalParentKey] ?? [] as $chapter) {
				$chapterId = $chapter->getId();
				$effectiveParentIds[$chapterId] = $effectiveParentId;

				if (!isset($included[$chapterId])) {
					foreach ($build($effectiveParentId, $chapterId) as $promoted) {
						$nodes[] = $promoted;
					}
					continue;
				}

				/** @var list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $children */
				$children = $build($chapterId, $chapterId);
				$nodes[] = [
					'chapter' => $chapter,
					'children' => $children,
					'effectiveParentId' => $effectiveParentId,
				];
			}

			return $nodes;
		};

		return [
			'tree' => $build(null, 0),
			'effectiveParentIds' => $effectiveParentIds,
		];
	}
}
