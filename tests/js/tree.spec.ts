import { describe, expect, it } from 'vitest'
import type { Chapter } from '../../src/types'
import { collectChapterIds, containsChapter, findChapter, flattenChapters } from '../../src/utils/tree'

function chapter(id: number, parentId: number | null, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId: 1,
		parentId,
		name: `Chapter ${id}`,
		fileId: id,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

const tree: Chapter[] = [
	chapter(1, null, [
		chapter(2, 1, [chapter(3, 2)]),
		chapter(4, 1),
	]),
	chapter(5, null),
]

describe('chapter tree helpers', () => {
	it('finds nested chapters by id', () => {
		expect(findChapter(tree, 3)?.name).toBe('Chapter 3')
		expect(findChapter(tree, 5)?.name).toBe('Chapter 5')
		expect(findChapter(tree, 99)).toBeNull()
		expect(findChapter(tree, null)).toBeNull()
	})

	it('flattens the tree in depth-first order', () => {
		expect(flattenChapters(tree).map((item) => item.id)).toEqual([1, 2, 3, 4, 5])
	})

	it('collects a subtree and checks containment', () => {
		const root = tree[0]
		expect(collectChapterIds(root)).toEqual([1, 2, 3, 4])
		expect(containsChapter(root, 3)).toBe(true)
		expect(containsChapter(root, 5)).toBe(false)
		expect(containsChapter(root, null)).toBe(false)
	})
})
