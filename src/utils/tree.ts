import type { Chapter } from '../types'

export function findChapter(chapters: Chapter[], chapterId: number | null): Chapter | null {
	if (chapterId === null) {
		return null
	}

	for (const chapter of chapters) {
		if (chapter.id === chapterId) {
			return chapter
		}

		const child = findChapter(chapter.children, chapterId)
		if (child !== null) {
			return child
		}
	}

	return null
}

export function flattenChapters(chapters: Chapter[]): Chapter[] {
	const flattened: Chapter[] = []
	for (const chapter of chapters) {
		flattened.push(chapter, ...flattenChapters(chapter.children))
	}

	return flattened
}

export function collectChapterIds(chapter: Chapter): number[] {
	return [chapter.id, ...chapter.children.flatMap(collectChapterIds)]
}

export function containsChapter(chapter: Chapter, chapterId: number | null): boolean {
	return chapterId !== null && collectChapterIds(chapter).includes(chapterId)
}
