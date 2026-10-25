<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Chapter;

class NumberingService {
	/**
	 * @param list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $tree
	 * @return list<array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string}>
	 */
	public function number(array $tree): array {
		return $this->apply($tree, 0, '');
	}

	/**
	 * @param list<array{chapter: Chapter, children: array, effectiveParentId: ?int}> $tree
	 * @return list<array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string}>
	 */
	private function apply(array $tree, int $depth, string $prefix): array {
		/** @var list<array{chapter: Chapter, children: array, effectiveParentId: ?int, number: string}> $numbered */
		$numbered = [];
		foreach ($tree as $index => $node) {
			$number = $depth === 0
				? ($index + 1) . '.'
				: $prefix . ($index + 1);

			/** @psalm-suppress ArgumentTypeCoercion */
			$children = $this->apply($node['children'], $depth + 1, rtrim($number, '.') . '.');

			$numbered[] = [
				'chapter' => $node['chapter'],
				'children' => $children,
				'effectiveParentId' => $node['effectiveParentId'],
				'number' => $number,
			];
		}

		return $numbered;
	}
}
