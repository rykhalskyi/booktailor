import { describe, expect, it } from 'vitest'
import type { Asset, Tag } from '../../src/types'
import { filterAssetsByTag } from '../../src/utils/assets'

function tag(id: number, name: string): Tag {
	return { id, bookId: 1, name, slug: name.toLowerCase(), color: '#2563eb' }
}

function asset(id: number, tags: Tag[]): Asset {
	return {
		id,
		bookId: 1,
		chapterId: 1,
		name: `Asset ${id}`,
		fileName: `Asset ${id}.md`,
		fileId: id,
		path: `BookTailor/Book/Chapter/Asset ${id}.md`,
		tags,
		includeInComposition: false,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

describe('filterAssetsByTag', () => {
	const raw = tag(1, 'Raw')
	const summary = tag(2, 'Summary')
	const assets = [
		asset(10, [raw]),
		asset(11, [raw, summary]),
		asset(12, []),
	]

	it('returns every asset when no tag is selected', () => {
		expect(filterAssetsByTag(assets, null)).toEqual(assets)
	})

	it('keeps only assets carrying the selected tag', () => {
		expect(filterAssetsByTag(assets, summary.id).map((item) => item.id)).toEqual([11])
	})

	it('returns an empty list when nothing matches', () => {
		expect(filterAssetsByTag(assets, 99)).toEqual([])
	})
})
