<?php

declare(strict_types=1);

namespace OCA\BookTailor\Tests\Unit\Service;

use OCA\BookTailor\Entity\Asset;
use OCA\BookTailor\Service\CompositionRuleResolver;
use PHPUnit\Framework\TestCase;

final class CompositionRuleResolverTest extends TestCase {
	private CompositionRuleResolver $resolver;

	protected function setUp(): void {
		$this->resolver = new CompositionRuleResolver();
	}

	public function testEmptyRulesSelectAllAssetsInNameOrder(): void {
		$assets = [$this->asset(2, 'zebra'), $this->asset(1, 'alpha')];

		$selected = $this->resolver->select($assets, [], []);

		$this->assertSame([1, 2], $this->ids($selected));
	}

	public function testFirstMatchingNonFallbackRuleWins(): void {
		$assets = [
			$this->asset(1, 'summary', [10]),
			$this->asset(2, 'raw', [20]),
			$this->asset(3, 'raw two', [20]),
		];
		$tags = $this->tagMap($assets);
		$rules = [
			['tagId' => 10, 'priority' => 1, 'fallback' => false],
			['tagId' => 20, 'priority' => 2, 'fallback' => false],
		];

		$this->assertSame([1], $this->ids($this->resolver->select($assets, $tags, $rules)));
	}

	public function testFallbackIsUsedWhenNoNormalRuleMatches(): void {
		$assets = [
			$this->asset(1, 'raw', [20]),
			$this->asset(2, 'raw two', [20]),
		];
		$tags = $this->tagMap($assets);
		$rules = [
			['tagId' => 10, 'priority' => 1, 'fallback' => false],
			['tagId' => 20, 'priority' => 2, 'fallback' => true],
		];

		$this->assertSame([1, 2], $this->ids($this->resolver->select($assets, $tags, $rules)));
	}

	public function testNormalRuleIsPreferredOverFallbackEvenWithLowerPriority(): void {
		$assets = [
			$this->asset(1, 'summary', [10]),
			$this->asset(2, 'raw', [20]),
		];
		$tags = $this->tagMap($assets);
		$rules = [
			['tagId' => 20, 'priority' => 1, 'fallback' => true],
			['tagId' => 10, 'priority' => 2, 'fallback' => false],
		];

		$this->assertSame([1], $this->ids($this->resolver->select($assets, $tags, $rules)));
	}

	public function testNoMatchingRuleReturnsNoAssets(): void {
		$assets = [$this->asset(1, 'raw', [20])];
		$tags = $this->tagMap($assets);
		$rules = [
			['tagId' => 10, 'priority' => 1, 'fallback' => false],
		];

		$this->assertSame([], $this->resolver->select($assets, $tags, $rules));
	}

	/**
	 * @param list<int> $tagIds
	 */
	private function asset(int $id, string $name, array $tagIds = []): Asset {
		$asset = new Asset();
		$asset->setId($id);
		$asset->setName($name);
		$asset->setChapterId(1);
		$asset->setFileName($name . '.md');

		return $asset;
	}

	/**
	 * @param list<Asset> $assets
	 * @return array<int, list<int>>
	 */
	private function tagMap(array $assets): array {
		$map = [];
		foreach ($assets as $asset) {
			$map[$asset->getId()] = match ($asset->getName()) {
				'summary' => [10],
				default => [20],
			};
		}

		return $map;
	}

	/**
	 * @param list<Asset> $assets
	 * @return list<int>
	 */
	private function ids(array $assets): array {
		return array_map(static fn (Asset $asset): int => $asset->getId(), $assets);
	}
}
