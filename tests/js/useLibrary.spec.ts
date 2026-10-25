import { flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../src/api/client', () => {
	class ApiError extends Error {
		public readonly status: number

		public constructor(message: string, status: number) {
			super(message)
			this.name = 'ApiError'
			this.status = status
		}
	}

	return {
		ApiError,
		api: {
			books: {
				list: vi.fn(),
				create: vi.fn(),
			},
			structures: {
				list: vi.fn(),
				create: vi.fn(),
				update: vi.fn(),
				remove: vi.fn(),
				entries: vi.fn(),
				replaceEntries: vi.fn(),
			},
			chapters: {
				tree: vi.fn(),
				create: vi.fn(),
				rename: vi.fn(),
				move: vi.fn(),
				remove: vi.fn(),
			},
			assets: {
				list: vi.fn(),
				create: vi.fn(),
				upload: vi.fn(),
				remove: vi.fn(),
			},
		},
	}
})

import { api } from '../../src/api/client'
import { createLibraryStore } from '../../src/stores/useLibrary'
import type { Asset, Book, Chapter, Structure } from '../../src/types'

function deferred<T>() {
	let resolve!: (value: T) => void
	const promise = new Promise<T>((resolveValue) => {
		resolve = resolveValue
	})

	return { promise, resolve }
}

function book(id: number): Book {
	return {
		id,
		name: `Book ${id}`,
		description: null,
		rootFileId: id,
		path: null,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function structure(id: number, bookId: number, isDefault = true): Structure {
	return {
		id,
		bookId,
		name: `Structure ${id}`,
		isDefault,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function chapter(id: number, bookId: number, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId,
		parentId: null,
		name: `Chapter ${id}`,
		fileId: id,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function asset(id: number, chapterId: number): Asset {
	return {
		id,
		bookId: 1,
		chapterId,
		name: `Asset ${id}`,
		fileName: `Asset ${id}.md`,
		fileId: id,
		tags: [],
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

beforeEach(() => {
	vi.mocked(api.books.list).mockResolvedValue([])
	vi.mocked(api.structures.list).mockResolvedValue([])
	vi.mocked(api.structures.entries).mockResolvedValue([])
	vi.mocked(api.structures.replaceEntries).mockResolvedValue([])
	vi.mocked(api.chapters.tree).mockResolvedValue([])
	vi.mocked(api.assets.list).mockResolvedValue([])
})

describe('library store', () => {
	it('discards a late chapter tree from a previous book', async () => {
		const store = createLibraryStore()
		const bookA = book(1)
		const bookB = book(2)
		const structureA = structure(10, 1)
		const structureB = structure(20, 2)
		const treeA = deferred<Chapter[]>()
		const treeB = deferred<Chapter[]>()

		vi.mocked(api.structures.list).mockImplementation(async (bookId) => (
			bookId === bookA.id ? [structureA] : [structureB]
		))
		vi.mocked(api.chapters.tree).mockImplementation((bookId) => (
			bookId === bookA.id ? treeA.promise : treeB.promise
		))

		const selectA = store.selectBook(bookA)
		await flushPromises()
		const selectB = store.selectBook(bookB)
		await flushPromises()

		treeB.resolve([chapter(200, 2)])
		await flushPromises()

		expect(store.selectedBookId.value).toBe(bookB.id)
		expect(store.chapters.value.map((item) => item.id)).toEqual([200])

		treeA.resolve([chapter(100, 1)])
		await Promise.all([selectA, selectB])

		expect(store.selectedBookId.value).toBe(bookB.id)
		expect(store.structures.value).toEqual([structureB])
		expect(store.chapters.value.map((item) => item.id)).toEqual([200])
	})

	it('discards a late structure list from a previous book', async () => {
		const store = createLibraryStore()
		const bookA = book(1)
		const bookB = book(2)
		const structuresA = deferred<Structure[]>()
		const structuresB = deferred<Structure[]>()

		vi.mocked(api.structures.list).mockImplementation((bookId) => (
			bookId === bookA.id ? structuresA.promise : structuresB.promise
		))

		const selectA = store.selectBook(bookA)
		const selectB = store.selectBook(bookB)

		structuresB.resolve([structure(20, 2)])
		await flushPromises()
		structuresA.resolve([structure(10, 1)])
		await Promise.all([selectA, selectB])

		expect(store.selectedBookId.value).toBe(bookB.id)
		expect(store.structures.value).toEqual([structure(20, 2)])
		expect(store.selectedStructureId.value).toBe(20)
	})

	it('tracks asset loading per chapter and keeps the latest selection', async () => {
		const store = createLibraryStore()
		const assetsA = deferred<Asset[]>()
		const assetsB = deferred<Asset[]>()

		vi.mocked(api.assets.list).mockImplementation((chapterId) => (
			chapterId === 1 ? assetsA.promise : assetsB.promise
		))

		const selectA = store.selectChapter(chapter(1, 1))
		const selectB = store.selectChapter(chapter(2, 1))

		expect(store.selectedAssetsLoading.value).toBe(true)

		assetsB.resolve([asset(20, 2)])
		await flushPromises()

		expect(store.selectedChapterId.value).toBe(2)
		expect(store.selectedAssets.value.map((item) => item.id)).toEqual([20])
		expect(store.selectedAssetsLoading.value).toBe(false)
		expect(store.loadingAssets.value[1]).toBe(true)

		assetsA.resolve([asset(10, 1)])
		await Promise.all([selectA, selectB])

		expect(store.selectedChapterId.value).toBe(2)
		expect(store.selectedAssets.value.map((item) => item.id)).toEqual([20])
		expect(store.loadingAssets.value[1]).toBeUndefined()
	})

	it('does not open a dialog while a mutation is running', () => {
		const store = createLibraryStore()

		store.loading.mutation = true
		store.openDialog({ type: 'book-create' })
		expect(store.dialog.value).toBeNull()

		store.loading.mutation = false
		store.openDialog({ type: 'book-create' })
		expect(store.dialog.value).toEqual({ type: 'book-create' })
	})

	it('loads the structure editor for the requested structure', async () => {
		const store = createLibraryStore()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		vi.mocked(api.chapters.tree).mockResolvedValue([chapter(1, 1)])
		vi.mocked(api.structures.entries).mockResolvedValue([
			{ chapterId: 1, included: true, position: 1, effectiveParentId: null },
		])

		await store.loadStructureEditor(10)

		expect(store.editorChapters.value.map((item) => item.id)).toEqual([1])
		expect(store.editorEntries.value).toHaveLength(1)
		expect(store.editorStructureId.value).toBe(10)
	})

	it('blocks entry saves when the editor failed to load', async () => {
		const store = createLibraryStore()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 20
		vi.mocked(api.chapters.tree).mockRejectedValue(new Error('offline'))
		vi.mocked(api.structures.entries).mockRejectedValue(new Error('offline'))

		await store.loadStructureEditor(20)

		expect(store.editorChapters.value).toEqual([])
		expect(store.editorEntries.value).toEqual([])
		expect(store.editorStructureId.value).toBeNull()

		const saved = await store.saveStructureEntries(20, [
			{ chapterId: 1, included: true, position: 1, effectiveParentId: null },
		])

		expect(saved).toBe(false)
		expect(api.structures.replaceEntries).not.toHaveBeenCalled()
	})

	it('does not save entries for a structure that is not loaded in the editor', async () => {
		const store = createLibraryStore()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		vi.mocked(api.chapters.tree).mockResolvedValue([chapter(1, 1)])
		vi.mocked(api.structures.entries).mockResolvedValue([
			{ chapterId: 1, included: true, position: 1, effectiveParentId: null },
		])
		await store.loadStructureEditor(10)

		const saved = await store.saveStructureEntries(20, [
			{ chapterId: 1, included: false, position: 1, effectiveParentId: null },
		])

		expect(saved).toBe(false)
		expect(api.structures.replaceEntries).not.toHaveBeenCalled()
	})

	it('moves a chapter and reloads the tree and editor', async () => {
		const store = createLibraryStore()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		vi.mocked(api.chapters.move).mockResolvedValue(chapter(2, 1))
		vi.mocked(api.chapters.tree).mockResolvedValue([chapter(1, 1)])
		vi.mocked(api.structures.entries).mockResolvedValue([
			{ chapterId: 1, included: true, position: 1, effectiveParentId: null },
		])

		const moved = await store.moveChapter(2, null)

		expect(moved).toBe(true)
		expect(api.chapters.move).toHaveBeenCalledWith(2, null)
		expect(api.chapters.tree).toHaveBeenCalledWith(1, 10)
		expect(api.structures.entries).toHaveBeenCalledWith(10)
		expect(store.editorStructureId.value).toBe(10)
	})

	it('creates a structure, selects it, and loads its editor', async () => {
		const store = createLibraryStore()
		const created = structure(20, 1, false)
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		vi.mocked(api.structures.create).mockResolvedValue(created)
		vi.mocked(api.structures.list).mockResolvedValue([structure(10, 1), created])
		vi.mocked(api.chapters.tree).mockResolvedValue([])
		vi.mocked(api.structures.entries).mockResolvedValue([])

		const result = await store.createStructure('Study')

		expect(result).toBe(true)
		expect(api.structures.create).toHaveBeenCalledWith(1, 'Study')
		expect(store.selectedStructureId.value).toBe(20)
		expect(store.structures.value.map((item) => item.id)).toEqual([10, 20])
		expect(store.editorStructureId.value).toBe(20)
	})

	it('renames a structure and refreshes the list', async () => {
		const store = createLibraryStore()
		const renamed = { ...structure(20, 1), name: 'Final' }
		store.selectedBookId.value = 1
		vi.mocked(api.structures.update).mockResolvedValue(renamed)
		vi.mocked(api.structures.list).mockResolvedValue([structure(10, 1), renamed])

		const result = await store.renameStructure(structure(20, 1), 'Final')

		expect(result).toBe(true)
		expect(api.structures.update).toHaveBeenCalledWith(20, 'Final')
		expect(store.structures.value.find((item) => item.id === 20)?.name).toBe('Final')
	})

	it('falls back to the default structure after deleting the selected one', async () => {
		const store = createLibraryStore()
		const fallback = structure(10, 1)
		const custom = structure(20, 1, false)
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 20
		store.structures.value = [fallback, custom]
		vi.mocked(api.structures.remove).mockResolvedValue(undefined)
		vi.mocked(api.structures.list).mockResolvedValue([fallback])
		vi.mocked(api.chapters.tree).mockResolvedValue([])
		vi.mocked(api.structures.entries).mockResolvedValue([])

		const result = await store.deleteStructure(custom)

		expect(result).toBe(true)
		expect(api.structures.remove).toHaveBeenCalledWith(20)
		expect(store.selectedStructureId.value).toBe(10)
		expect(store.structures.value).toEqual([fallback])
	})
})
