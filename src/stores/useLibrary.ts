import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { Asset, Book, Chapter, DialogState, Structure } from '../types'
import { collectChapterIds, findChapter } from '../utils/tree'

const books = ref<Book[]>([])
const selectedBookId = ref<number | null>(null)
const structures = ref<Structure[]>([])
const selectedStructureId = ref<number | null>(null)
const chapters = ref<Chapter[]>([])
const selectedChapterId = ref<number | null>(null)
const assetsByChapter = ref<Record<number, Asset[]>>({})
const expandedChapterIds = ref<Record<number, boolean>>({})
const dialog = ref<DialogState | null>(null)
const error = ref<string | null>(null)
const loading = reactive({
	books: false,
	tree: false,
	assets: false,
	mutation: false,
})

const selectedBook = computed<Book | null>(() => books.value.find((book) => book.id === selectedBookId.value) ?? null)
const selectedChapter = computed<Chapter | null>(() => findChapter(chapters.value, selectedChapterId.value))
const selectedStructure = computed<Structure | null>(() => structures.value.find((structure) => structure.id === selectedStructureId.value) ?? null)
const selectedAssets = computed<Asset[]>(() => selectedChapterId.value === null
	? []
	: assetsByChapter.value[selectedChapterId.value] ?? [])

function messageFor(errorValue: unknown): string {
	if (errorValue instanceof ApiError || errorValue instanceof Error) {
		return errorValue.message
	}

	return 'Something went wrong. Please try again.'
}

function resetBookData(): void {
	structures.value = []
	selectedStructureId.value = null
	chapters.value = []
	selectedChapterId.value = null
	assetsByChapter.value = {}
	expandedChapterIds.value = {}
}

async function loadBooks(): Promise<void> {
	loading.books = true
	error.value = null
	try {
		books.value = await api.books.list()
	} catch (errorValue) {
		error.value = messageFor(errorValue)
	} finally {
		loading.books = false
	}
}

async function loadTree(): Promise<void> {
	if (selectedBookId.value === null) {
		return
	}

	loading.tree = true
	try {
		chapters.value = await api.chapters.tree(
			selectedBookId.value,
			selectedStructureId.value ?? undefined,
		)
		if (selectedChapterId.value !== null && findChapter(chapters.value, selectedChapterId.value) === null) {
			selectedChapterId.value = null
		}
	} catch (errorValue) {
		chapters.value = []
		error.value = messageFor(errorValue)
	} finally {
		loading.tree = false
	}
}

async function loadAssets(chapterId: number): Promise<void> {
	loading.assets = true
	try {
		assetsByChapter.value = {
			...assetsByChapter.value,
			[chapterId]: await api.assets.list(chapterId),
		}
	} catch (errorValue) {
		error.value = messageFor(errorValue)
	} finally {
		loading.assets = false
	}
}

async function selectChapter(chapter: Chapter): Promise<void> {
	selectedChapterId.value = chapter.id
	error.value = null
	await loadAssets(chapter.id)
}

async function selectBook(book: Book): Promise<void> {
	selectedBookId.value = book.id
	resetBookData()
	error.value = null

	try {
		structures.value = await api.structures.list(book.id)
		const preferred = structures.value.find((structure) => structure.isDefault) ?? structures.value[0] ?? null
		selectedStructureId.value = preferred?.id ?? null
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		structures.value = []
		selectedStructureId.value = null
	}

	await loadTree()
	if (error.value === null && selectedChapterId.value === null && chapters.value.length > 0) {
		await selectChapter(chapters.value[0])
	}
}

async function selectStructure(structureId: number | null): Promise<void> {
	selectedStructureId.value = structureId
	selectedChapterId.value = null
	assetsByChapter.value = {}
	error.value = null
	await loadTree()
	if (error.value === null && chapters.value.length > 0) {
		await selectChapter(chapters.value[0])
	}
}

function toggleChapter(chapterId: number, current: boolean): void {
	expandedChapterIds.value = {
		...expandedChapterIds.value,
		[chapterId]: !current,
	}
}

async function initialize(): Promise<void> {
	await loadBooks()
	if (books.value.length === 0) {
		selectedBookId.value = null
		resetBookData()
		return
	}

	const current = books.value.find((book) => book.id === selectedBookId.value) ?? books.value[0]
	await selectBook(current)
}

async function createBook(name: string): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		const created = await api.books.create(name)
		await loadBooks()
		const book = books.value.find((candidate) => candidate.id === created.id) ?? created
		await selectBook(book)
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function createChapter(name: string, parentId: number | null): Promise<boolean> {
	if (selectedBookId.value === null || loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		const created = await api.chapters.create(selectedBookId.value, name, parentId)
		if (parentId !== null) {
			expandedChapterIds.value = { ...expandedChapterIds.value, [parentId]: true }
		}
		await loadTree()
		const chapter = findChapter(chapters.value, created.id)
		if (chapter !== null) {
			await selectChapter(chapter)
		}
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function renameChapter(chapter: Chapter, name: string): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		await api.chapters.rename(chapter.id, name)
		await loadTree()
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function deleteChapter(chapter: Chapter): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		const deletedIds = collectChapterIds(chapter)
		await api.chapters.remove(chapter.id)
		if (selectedChapterId.value !== null && deletedIds.includes(selectedChapterId.value)) {
			selectedChapterId.value = null
		}
		const remainingAssets = { ...assetsByChapter.value }
		for (const id of deletedIds) {
			delete remainingAssets[id]
		}
		assetsByChapter.value = remainingAssets
		await loadTree()
		if (selectedChapterId.value === null && chapters.value.length > 0) {
			await selectChapter(chapters.value[0])
		}
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function createAsset(chapterId: number, name: string): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		await api.assets.create(chapterId, name)
		selectedChapterId.value = chapterId
		await loadAssets(chapterId)
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function uploadAsset(chapterId: number, file: File): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		await api.assets.upload(chapterId, file)
		selectedChapterId.value = chapterId
		await loadAssets(chapterId)
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

async function deleteAsset(asset: Asset): Promise<boolean> {
	if (loading.mutation.value) {
		return false
	}

	loading.mutation = true
	error.value = null
	try {
		await api.assets.remove(asset.id)
		await loadAssets(asset.chapterId)
		return true
	} catch (errorValue) {
		error.value = messageFor(errorValue)
		return false
	} finally {
		loading.mutation = false
	}
}

function openDialog(state: DialogState): void {
	dialog.value = state
	error.value = null
}

function closeDialog(): void {
	dialog.value = null
	error.value = null
}

function clearError(): void {
	error.value = null
}

export function useLibrary() {
	return {
		assetsByChapter,
		books,
		chapters,
		dialog,
		error,
		expandedChapterIds,
		loading,
		selectedAssets,
		selectedBook,
		selectedBookId,
		selectedChapter,
		selectedChapterId,
		selectedStructure,
		selectedStructureId,
		structures,
		clearError,
		closeDialog,
		createAsset,
		createBook,
		createChapter,
		deleteAsset,
		deleteChapter,
		initialize,
		loadBooks,
		loadTree,
		openDialog,
		renameChapter,
		selectBook,
		selectChapter,
		selectStructure,
		toggleChapter,
		uploadAsset,
	}
}
