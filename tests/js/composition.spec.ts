import { describe, expect, it } from 'vitest'
import type { Asset, CompositionRule, Tag } from '../../src/types'
import { pickAssetsForChapter } from '../../src/utils/composition'

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
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function rule(tagId: number, priority: number, fallback = false): CompositionRule {
	return { tagId, priority, fallback }
}

const raw = tag(1, 'Raw')
const summary = tag(2, 'Summary')
const draft = tag(3, 'Draft')
const assets = [
	asset(10, [raw]),
	asset(11, [raw, summary]),
	asset(12, [draft]),
]

describe('pickAssetsForChapter', () => {
	it('returns no picks when there are no rules', () => {
		expect(pickAssetsForChapter(assets, []).size).toBe(0)
	})

	it('picks every asset carrying the first matching normal tag', () => {
		const picks = pickAssetsForChapter(assets, [
			rule(raw.id, 1),
			rule(summary.id, 2),
		])

		expect([...picks.keys()].sort()).toEqual([10, 11])
		expect(picks.get(10)?.rulePriority).toBe(1)
		expect(picks.get(11)?.tag?.name).toBe('Raw')
	})

	it('uses rule priority instead of array order', () => {
		const picks = pickAssetsForChapter(assets, [
			rule(raw.id, 5),
			rule(summary.id, 1),
		])

		expect([...picks.keys()]).toEqual([11])
		expect(picks.get(11)?.rulePriority).toBe(1)
	})

	it('only uses fallback rules when no normal rule matches', () => {
		const fallbackOnly = pickAssetsForChapter(assets, [
			rule(draft.id, 1, true),
		])

		expect([...fallbackOnly.keys()]).toEqual([12])

		const normalWins = pickAssetsForChapter(assets, [
			rule(raw.id, 1),
			rule(draft.id, 2, true),
		])

		expect([...normalWins.keys()].sort()).toEqual([10, 11])
	})
})
