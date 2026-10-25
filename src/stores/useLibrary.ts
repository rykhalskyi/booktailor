import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { Asset, Book, Chapter, DialogState, Structure, StructureEntry } from '../types'
import { collectChapterIds, findChapter } from '../utils/tree'

function messageFor(errorValue: unknown): string {
	if (errorValue instanceof ApiError || errorValue instanceof Error) {
		return errorValue.message
	}

	return 'Something went wrong. Please try again.'
}

export function createLibraryStore() {
	const books = ref<Book[]>([])
	const selectedBookId = ref<number | null>(null)
	const structures = ref<Structure[]>([])
	const selectedStructureId = ref<number | null>(null)
	const chapters = ref<Chapter[]>([])
	const selectedChapterId = ref<number | null>(null)
	const assetsByChapter = ref<Record<number, Asset[]>>({})
	const loadingAssets = ref<Record<number, boolean>>({})
	const expandedChapterIds = ref<Record<number, boolean>>({})
	const dialog = ref<DialogState | null>(null)
	const error = ref<string | null>(null)
	const loading = reactive({
		books: false,
		tree: false,
		mutation: false,
	})
	const structureManagerOpen = ref(false)
	const editorChapters = ref<Chapter[]>([])
	const editorEntries = ref<StructureEntry[]>([])
	const editorLoading = reactive({
		chapters: false,
		entries: false,
	})

	let booksRequest = 0
	let selectionEpoch = 0
	let editorEpoch = 0
	const assetRequests: Record<number, number> = {}

	const selectedBook = computed<Book | null>(() => books.value.find((book) => book.id === selectedBookId.value) ?? null)
	const selectedChapter = computed<Chapter | null>(() => findChapter(chapters.value, selectedChapterId.value))
	const selectedStructure = computed<Structure | null>(() => structures.value.find((structure) => structure.id === selectedStructureId.value) ?? null)
	const selectedAssets = computed<Asset[]>(() => selectedChapterId.value === null
		? []
		: assetsByChapter.value[selectedChapterId.value] ?? [])
	const selectedAssetsLoading = computed<boolean>(() => selectedChapterId.value === null
		? false
		: loadingAssets.value[selectedChapterId.value] ?? false)

	function resetBookData(): void {
		structures.value = []
		selectedStructureId.value = null
		chapters.value = []
		selectedChapterId.value = null
		assetsByChapter.value = {}
		loadingAssets.value = {}
		expandedChapterIds.value = {}
		editorEpoch += 1
		editorChapters.value = []
		editorEntries.value = []
		editorLoading.chapters = false
		editorLoading.entries = false
	}

	async function loadBooks(): Promise<void> {
		const request = ++booksRequest
		loading.books = true
		error.value = null
		try {
			const loaded = await api.books.list()
			if (request === booksRequest) {
				books.value = loaded
			}
		} catch (errorValue) {
			if (request === booksRequest) {
				error.value = messageFor(errorValue)
			}
		} finally {
			if (request === booksRequest) {
				loading.books = false
			}
		}
	}

	async function loadTree(
		bookId: number | null = selectedBookId.value,
		structureId: number | null = selectedStructureId.value,
		epoch: number = selectionEpoch,
	): Promise<void> {
		if (bookId === null) {
			return
		}

		loading.tree = true
		try {
			const tree = await api.chapters.tree(bookId, structureId ?? undefined)
			if (epoch !== selectionEpoch || selectedBookId.value !== bookId || selectedStructureId.value !== structureId) {
				return
			}

			chapters.value = tree
			if (selectedChapterId.value !== null && findChapter(tree, selectedChapterId.value) === null) {
				selectedChapterId.value = null
			}
		} catch (errorValue) {
			if (epoch !== selectionEpoch || selectedBookId.value !== bookId) {
				return
			}

			chapters.value = []
			error.value = messageFor(errorValue)
		} finally {
			if (epoch === selectionEpoch && selectedBookId.value === bookId) {
				loading.tree = false
			}
		}
	}

	async function loadAssets(chapterId: number): Promise<void> {
		const request = (assetRequests[chapterId] ?? 0) + 1
		assetRequests[chapterId] = request
		loadingAssets.value = {
			...loadingAssets.value,
			[chapterId]: true,
		}

		try {
			const assets = await api.assets.list(chapterId)
			if (assetRequests[chapterId] !== request) {
				return
			}
			assetsByChapter.value = {
				...assetsByChapter.value,
				[chapterId]: assets,
			}
		} catch (errorValue) {
			if (assetRequests[chapterId] === request && selectedChapterId.value === chapterId) {
				error.value = messageFor(errorValue)
			}
		} finally {
			if (assetRequests[chapterId] === request) {
				const remaining = { ...loadingAssets.value }
				delete remaining[chapterId]
				loadingAssets.value = remaining
			}
		}
	}

	async function selectChapter(chapter: Chapter): Promise<void> {
		selectedChapterId.value = chapter.id
		error.value = null
		await loadAssets(chapter.id)
	}

	async function selectBook(book: Book): Promise<void> {
		const epoch = ++selectionEpoch
		selectedBookId.value = book.id
		resetBookData()
		error.value = null

		try {
			const loaded = await api.structures.list(book.id)
			if (epoch !== selectionEpoch || selectedBookId.value !== book.id) {
				return
			}
			structures.value = loaded
			const preferred = loaded.find((structure) => structure.isDefault) ?? loaded[0] ?? null
			selectedStructureId.value = preferred?.id ?? null
		} catch (errorValue) {
			if (epoch !== selectionEpoch || selectedBookId.value !== book.id) {
				return
			}
			error.value = messageFor(errorValue)
			structures.value = []
			selectedStructureId.value = null
		}

		if (epoch !== selectionEpoch || selectedBookId.value !== book.id) {
			return
		}

		await loadTree(book.id, selectedStructureId.value, epoch)
		if (epoch !== selectionEpoch || selectedBookId.value !== book.id) {
			return
		}

		if (error.value === null && selectedChapterId.value === null && chapters.value.length > 0) {
			await selectChapter(chapters.value[0])
		}
	}

	async function selectStructure(structureId: number | null): Promise<void> {
		const bookId = selectedBookId.value
		if (bookId === null) {
			return
		}

		const epoch = ++selectionEpoch
		selectedStructureId.value = structureId
		selectedChapterId.value = null
		assetsByChapter.value = {}
		loadingAssets.value = {}
		error.value = null
		await loadTree(bookId, structureId, epoch)
		if (epoch !== selectionEpoch) {
			return
		}

		if (error.value === null && chapters.value.length > 0) {
			await selectChapter(chapters.value[0])
		}
	}

	function openStructureManager(): void {
		if (selectedBookId.value === null) {
			return
		}

		structureManagerOpen.value = true
		error.value = null
		loadStructureEditor()
	}

	function closeStructureManager(): void {
		structureManagerOpen.value = false
		error.value = null
	}

	async function loadStructureEditor(structureId: number | null = selectedStructureId.value): Promise<void> {
		const epoch = ++editorEpoch
		const bookId = selectedBookId.value
		if (bookId === null || structureId === null) {
			editorChapters.value = []
			editorEntries.value = []
			editorLoading.chapters = false
			editorLoading.entries = false
			return
		}

		editorLoading.chapters = true
		editorLoading.entries = true
		try {
			const [allChapters, entries] = await Promise.all([
				api.chapters.tree(bookId),
				api.structures.entries(structureId),
			])
			if (epoch !== editorEpoch || selectedBookId.value !== bookId || selectedStructureId.value !== structureId) {
				return
			}

			editorChapters.value = allChapters
			editorEntries.value = entries
		} catch (errorValue) {
			if (epoch === editorEpoch) {
				error.value = messageFor(errorValue)
			}
		} finally {
			if (epoch === editorEpoch) {
				editorLoading.chapters = false
				editorLoading.entries = false
			}
		}
	}

	async function saveStructureEntries(structureId: number, entries: StructureEntry[]): Promise<boolean> {
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			const saved = await api.structures.replaceEntries(structureId, entries)
			if (selectedStructureId.value === structureId) {
				editorEntries.value = saved
				if (selectedBookId.value !== null) {
					await loadTree(selectedBookId.value, structureId)
				}
			}
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function createStructure(name: string): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			const created = await api.structures.create(bookId, name)
			const loaded = await api.structures.list(bookId)
			if (selectedBookId.value !== bookId) {
				return true
			}

			structures.value = loaded
			selectedStructureId.value = created.id
			await loadTree(bookId, created.id)
			if (selectedBookId.value !== bookId || selectedStructureId.value !== created.id) {
				return true
			}
			if (chapters.value.length > 0) {
				await selectChapter(chapters.value[0])
			}
			await loadStructureEditor(created.id)
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function renameStructure(structure: Structure, name: string): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.structures.update(structure.id, name)
			const loaded = await api.structures.list(bookId)
			if (selectedBookId.value === bookId) {
				structures.value = loaded
			}
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function deleteStructure(structure: Structure): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || structure.isDefault || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.structures.remove(structure.id)
			const loaded = await api.structures.list(bookId)
			if (selectedBookId.value !== bookId) {
				return true
			}

			structures.value = loaded
			if (selectedStructureId.value === structure.id) {
				const next = loaded.find((candidate) => candidate.isDefault) ?? loaded[0] ?? null
				selectedStructureId.value = next?.id ?? null
				if (next === null) {
					chapters.value = []
					selectedChapterId.value = null
					editorChapters.value = []
					editorEntries.value = []
					return true
				}

				await loadTree(bookId, next.id)
				if (chapters.value.length > 0) {
					await selectChapter(chapters.value[0])
				}
				await loadStructureEditor(next.id)
			}
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function moveChapter(chapterId: number, parentId: number | null): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.chapters.move(chapterId, parentId)
			if (selectedBookId.value !== bookId) {
				return true
			}

			await loadTree(bookId, selectedStructureId.value)
			if (selectedStructureId.value !== null) {
				await loadStructureEditor(selectedStructureId.value)
			}
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
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
		if (loading.mutation) {
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
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			const created = await api.chapters.create(bookId, name, parentId)
			if (parentId !== null) {
				expandedChapterIds.value = { ...expandedChapterIds.value, [parentId]: true }
			}
			if (selectedBookId.value !== bookId) {
				return true
			}

			await loadTree(bookId)
			if (selectedBookId.value !== bookId) {
				return true
			}

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
		const bookId = selectedBookId.value
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.chapters.rename(chapter.id, name)
			if (selectedBookId.value === bookId) {
				await loadTree(bookId)
			}
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function deleteChapter(chapter: Chapter): Promise<boolean> {
		const bookId = selectedBookId.value
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			const deletedIds = collectChapterIds(chapter)
			await api.chapters.remove(chapter.id)
			if (selectedBookId.value !== bookId) {
				return true
			}

			if (selectedChapterId.value !== null && deletedIds.includes(selectedChapterId.value)) {
				selectedChapterId.value = null
			}
			const remainingAssets = { ...assetsByChapter.value }
			const remainingLoading = { ...loadingAssets.value }
			for (const id of deletedIds) {
				delete remainingAssets[id]
				delete remainingLoading[id]
			}
			assetsByChapter.value = remainingAssets
			loadingAssets.value = remainingLoading
			await loadTree(bookId)
			if (selectedBookId.value !== bookId) {
				return true
			}

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
		const bookId = selectedBookId.value
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.assets.create(chapterId, name)
			if (selectedBookId.value !== bookId) {
				return true
			}
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
		const bookId = selectedBookId.value
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.assets.upload(chapterId, file)
			if (selectedBookId.value !== bookId) {
				return true
			}
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
		if (loading.mutation) {
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
		if (loading.mutation) {
			return
		}

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

	return {
		assetsByChapter,
		books,
		chapters,
		dialog,
		editorChapters,
		editorEntries,
		editorLoading,
		error,
		expandedChapterIds,
		loading,
		loadingAssets,
		selectedAssets,
		selectedAssetsLoading,
		selectedBook,
		selectedBookId,
		selectedChapter,
		selectedChapterId,
		selectedStructure,
		selectedStructureId,
		structures,
		structureManagerOpen,
		clearError,
		closeDialog,
		closeStructureManager,
		createAsset,
		createBook,
		createChapter,
		createStructure,
		deleteAsset,
		deleteChapter,
		deleteStructure,
		initialize,
		loadAssets,
		loadBooks,
		loadStructureEditor,
		loadTree,
		moveChapter,
		openDialog,
		openStructureManager,
		renameChapter,
		renameStructure,
		saveStructureEntries,
		selectBook,
		selectChapter,
		selectStructure,
		toggleChapter,
		uploadAsset,
	}
}

export type LibraryStore = ReturnType<typeof createLibraryStore>

const library = createLibraryStore()

export function useLibrary(): LibraryStore {
	return library
}
