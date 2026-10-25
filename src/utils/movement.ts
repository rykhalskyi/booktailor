import type { Chapter } from '../types'
import { flattenChapters } from './tree'

export interface MoveTarget {
	id: number | null
	label: string
}

export function moveTargetsFor(chapter: Chapter, chapters: Chapter[]): MoveTarget[] {
	const all = flattenChapters(chapters)
	const currentParentId = chapter.parentId
	const parent = currentParentId === null
		? null
		: all.find((candidate) => candidate.id === currentParentId) ?? null
	const grandParentId = parent?.parentId ?? null
	const grandParent = grandParentId === null
		? null
		: all.find((candidate) => candidate.id === grandParentId) ?? null

	const siblings = all
		.filter((candidate) => candidate.parentId === currentParentId)
		.sort((a, b) => a.id - b.id)
	const index = siblings.findIndex((candidate) => candidate.id === chapter.id)
	const previousSibling = index > 0 ? siblings[index - 1] : null

	const parentSiblings = currentParentId === null
		? []
		: all
			.filter((candidate) => candidate.parentId === grandParentId && candidate.id !== currentParentId)
			.sort((a, b) => a.id - b.id)

	const targets: MoveTarget[] = []
	const seen = new Set<string>()

	const add = (id: number | null, label: string): void => {
		const key = id === null ? 'root' : String(id)
		if (id === currentParentId || seen.has(key)) {
			return
		}
		seen.add(key)
		targets.push({ id, label })
	}

	if (grandParentId !== currentParentId) {
		add(grandParentId, grandParentId === null ? 'Book root' : `Under “${grandParent?.name ?? ''}”`)
	}
	if (previousSibling !== null) {
		add(previousSibling.id, `Under “${previousSibling.name}”`)
	}
	for (const sibling of parentSiblings) {
		add(sibling.id, `Under “${sibling.name}”`)
	}

	return targets
}
