<?php

declare(strict_types=1);

namespace OCA\BookTailor\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class AssetTagMapper {
	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct(
		private readonly IDBConnection $db,
	) {
	}

	/**
	 * @return list<int>
	 */
	public function findTagIdsByAsset(int $assetId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('tag_id')
			->from('booktailor_asset_tags')
			->where($qb->expr()->eq('asset_id', $qb->createNamedParameter($assetId, IQueryBuilder::PARAM_INT)))
			->orderBy('tag_id', 'ASC');

		/** @var list<array{tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();

		return array_map(static fn (array $row): int => (int)$row['tag_id'], $rows);
	}

	/**
	 * @param list<int> $assetIds
	 * @return array<int, list<int>>
	 */
	public function findTagIdsByAssetIds(array $assetIds): array {
		if ($assetIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('asset_id', 'tag_id')
			->from('booktailor_asset_tags')
			->where($qb->expr()->in('asset_id', $qb->createNamedParameter($assetIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('tag_id', 'ASC');

		/** @var list<array{asset_id: int|string, tag_id: int|string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$grouped = [];
		foreach ($rows as $row) {
			$grouped[(int)$row['asset_id']][] = (int)$row['tag_id'];
		}

		return $grouped;
	}

	/**
	 * @param list<int> $assetIds
	 * @return array<int, list<array{id: int, bookId: int, name: string, slug: string, color: string}>>
	 */
	public function findTagsByAssetIds(array $assetIds): array {
		if ($assetIds === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('at.asset_id', 't.id', 't.book_id', 't.name', 't.slug', 't.color')
			->from('booktailor_asset_tags', 'at')
			->innerJoin('at', 'booktailor_tags', 't', 'at.tag_id = t.id')
			->where($qb->expr()->in('at.asset_id', $qb->createNamedParameter($assetIds, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('t.name', 'ASC');

		/** @var list<array{asset_id: int|string, id: int|string, book_id: int|string, name: string, slug: string, color: string}> $rows */
		$rows = $qb->executeQuery()->fetchAll();
		$grouped = [];
		foreach ($rows as $row) {
			$grouped[(int)$row['asset_id']][] = [
				'id' => (int)$row['id'],
				'bookId' => (int)$row['book_id'],
				'name' => $row['name'],
				'slug' => $row['slug'],
				'color' => $row['color'],
			];
		}

		return $grouped;
	}

	public function deleteByAsset(int $assetId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('booktailor_asset_tags')
			->where($qb->expr()->eq('asset_id', $qb->createNamedParameter($assetId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * @param list<int> $tagIds
	 */
	public function replaceForAsset(int $assetId, array $tagIds): void {
		$this->deleteByAsset($assetId);
		if ($tagIds === []) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->insert('booktailor_asset_tags')
			->values([
				'asset_id' => $qb->createParameter('asset_id'),
				'tag_id' => $qb->createParameter('tag_id'),
			]);

		foreach ($tagIds as $tagId) {
			$qb->setParameter('asset_id', $assetId, IQueryBuilder::PARAM_INT);
			$qb->setParameter('tag_id', $tagId, IQueryBuilder::PARAM_INT);
			$qb->executeStatement();
		}
	}
}
