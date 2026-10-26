<?php

declare(strict_types=1);

namespace OCA\BookTailor\Service;

use OCA\BookTailor\Entity\Asset;

class CompositionRuleResolver {
	/**
	 * @param list<Asset> $assets
	 * @param array<int, list<int>> $tagIdsByAsset
	 * @param list<array{tagId: int, priority: int, fallback: bool}> $rules
	 * @return list<Asset>
	 */
	public function select(array $assets, array $tagIdsByAsset, array $rules): array {
		$assets = $this->sortAssets($assets);
		if ($rules === []) {
			return $assets;
		}

		foreach ([false, true] as $fallback) {
			foreach ($rules as $rule) {
				if ($rule['fallback'] !== $fallback) {
					continue;
				}

				$matched = [];
				foreach ($assets as $asset) {
					$tagIds = $tagIdsByAsset[$asset->getId()] ?? [];
					if (in_array($rule['tagId'], $tagIds, true)) {
						$matched[] = $asset;
					}
				}

				if ($matched !== []) {
					return $matched;
				}
			}
		}

		return [];
	}

	/**
	 * @param list<Asset> $assets
	 * @return list<Asset>
	 */
	private function sortAssets(array $assets): array {
		usort($assets, static function (Asset $a, Asset $b): int {
			return strcasecmp($a->getName(), $b->getName())
				?: ($a->getId() <=> $b->getId());
		});

		return $assets;
	}
}
