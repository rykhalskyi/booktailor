import { describe, expect, it } from 'vitest'
import type { Chapter } from '../../src/types'
import { moveTargetsFor } from '../../src/utils/movement'

function chapter(id: number, parentId: number | null, name: string, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId: 1,
		parentId,
		name,
		fileId: id,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

const childA1 = chapter(3, 1, 'Child A1')
const childA2 = chapter(4, 1, 'Child A2')
const rootA = chapter(1, null, 'Root A', [childA1, childA2])
const rootB = chapter(2, null, 'Root B')
const tree = [rootA, rootB]

describe('moveTargetsFor', () => {
	it('offers root promotion and parent siblings to a child', () => {
		expect(moveTargetsFor(childA1, tree).map((target) => target.id)).toEqual([null, 2])
	})

	it('includes the previous sibling as a demotion target', () => {
		expect(moveTargetsFor(childA2, tree).map((target) => target.id)).toEqual([null, 3, 2])
	})

	it('offers only the previous root sibling for demotion', () => {
		expect(moveTargetsFor(rootB, tree).map((target) => target.id)).toEqual([1])
	})

	it('returns no targets for the first root chapter', () => {
		expect(moveTargetsFor(rootA, tree)).toEqual([])
	})
})
