import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { Asset, Book, Capabilities, Chapter, Composition, DialogState, Structure, StructureEntry, Tag, Version } from '../types'
import { compositionFileName, saveBlob } from '../utils/compositions'
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
	const expandedAssetChapterIds = ref<Record<number, boolean>>({})
	const dialog = ref<DialogState | null>(null)
	const error = ref<string | null>(null)
	const loading = reactive({
		books: false,
		tree: false,
		mutation: false,
	})
	const structureManagerOpen = ref(false)
	const tagsManagerOpen = ref(false)
	const assetManagerOpen = ref(false)
	const composeOpen = ref(false)
	const compositionsOpen = ref(false)
	const settingsOpen = ref(false)
	const compositions = ref<Composition[]>([])
	const compositionsLoading = ref(false)
	const capabilities = ref<Capabilities>({ editor: 'text', versions: true })
	const tags = ref<Tag[]>([])
	const tagsLoading = ref(false)
	const historyAsset = ref<Asset | null>(null)
	const historyVersions = ref<Version[]>([])
	const historyLoading = ref(false)
	const historyBusy = ref(false)
	const historyError = ref<string | null>(null)
	const historyPreview = ref<{ revisionId: string, content: string } | null>(null)
	const historyPreviewLoading = ref(false)
	const editorChapters = ref<Chapter[]>([])
	const editorEntries = ref<StructureEntry[]>([])
	const editorStructureId = ref<number | null>(null)
	const editorLoading = reactive({
		chapters: false,
		entries: false,
	})

	let booksRequest = 0
	let selectionEpoch = 0
	let compositionsEpoch = 0
	let editorEpoch = 0
	let historyEpoch = 0
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
		composeOpen.value = false
		compositionsOpen.value = false
		compositions.value = []
		compositionsLoading.value = false
		compositionsEpoch += 1
		structures.value = []
		selectedStructureId.value = null
		chapters.value = []
		selectedChapterId.value = null
		assetsByChapter.value = {}
		loadingAssets.value = {}
		expandedChapterIds.value = {}
		expandedAssetChapterIds.value = {}
		tags.value = []
		historyEpoch += 1
		historyAsset.value = null
		historyVersions.value = []
		historyPreview.value = null
		historyError.value = null
		historyBusy.value = false
		historyLoading.value = false
		historyPreviewLoading.value = false
		editorEpoch += 1
		editorChapters.value = []
		editorEntries.value = []
		editorStructureId.value = null
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

	async function loadCapabilities(): Promise<void> {
		try {
			capabilities.value = await api.capabilities()
		} catch {
			capabilities.value = { editor: 'text', versions: true }
		}
	}

	async function loadTags(bookId: number): Promise<void> {
		tagsLoading.value = true
		try {
			const loaded = await api.tags.list(bookId)
			if (selectedBookId.value === bookId) {
				tags.value = loaded
			}
		} catch (errorValue) {
			if (selectedBookId.value === bookId) {
				error.value = messageFor(errorValue)
			}
		} finally {
			if (selectedBookId.value === bookId) {
				tagsLoading.value = false
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
		structureManagerOpen.value = false
		tagsManagerOpen.value = false
		assetManagerOpen.value = false
		composeOpen.value = false
		compositionsOpen.value = false
		settingsOpen.value = false
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

		await loadTags(book.id)
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
		expandedAssetChapterIds.value = {}
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
		tagsManagerOpen.value = false
		assetManagerOpen.value = false
		composeOpen.value = false
		compositionsOpen.value = false
		settingsOpen.value = false
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
		editorChapters.value = []
		editorEntries.value = []
		editorStructureId.value = null
		if (bookId === null || structureId === null) {
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
			editorStructureId.value = structureId
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
		if (
			loading.mutation
			|| editorStructureId.value !== structureId
			|| editorLoading.chapters
			|| editorLoading.entries
		) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			const saved = await api.structures.replaceEntries(structureId, entries)
			if (selectedStructureId.value === structureId && editorStructureId.value === structureId) {
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

	function openTagsManager(): void {
		if (selectedBookId.value === null) {
			return
		}

		tagsManagerOpen.value = true
		structureManagerOpen.value = false
		assetManagerOpen.value = false
		composeOpen.value = false
		compositionsOpen.value = false
		settingsOpen.value = false
		error.value = null
		loadTags(selectedBookId.value)
	}

	function closeTagsManager(): void {
		tagsManagerOpen.value = false
		error.value = null
	}

	async function openAssetManager(chapter: Chapter): Promise<void> {
		structureManagerOpen.value = false
		tagsManagerOpen.value = false
		composeOpen.value = false
		compositionsOpen.value = false
		settingsOpen.value = false
		assetManagerOpen.value = true
		selectedChapterId.value = chapter.id
		error.value = null
		await loadAssets(chapter.id)
	}

	function closeAssetManager(): void {
		assetManagerOpen.value = false
		error.value = null
	}

	async function createTag(name: string, color: string): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.tags.create(bookId, name, color)
			await loadTags(bookId)
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function renameTag(tag: Tag, name: string, color: string): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.tags.update(tag.id, name, color)
			await loadTags(bookId)
			await refreshLoadedAssets()
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function deleteTag(tag: Tag): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.tags.remove(tag.id)
			await loadTags(bookId)
			await refreshLoadedAssets()
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function assignAssetTags(asset: Asset, tagIds: number[]): Promise<boolean> {
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.tags.replaceForAsset(asset.id, tagIds)
			await loadAssets(asset.chapterId)
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function refreshLoadedAssets(): Promise<void> {
		const chapterIds = Object.keys(assetsByChapter.value).map(Number)
		await Promise.all(chapterIds.map(async (chapterId) => loadAssets(chapterId)))
	}

	async function openHistory(asset: Asset): Promise<void> {
		historyAsset.value = asset
		historyVersions.value = []
		historyPreview.value = null
		historyError.value = null
		await loadHistory(asset)
	}

	async function loadHistory(asset: Asset | null = historyAsset.value): Promise<void> {
		if (asset === null) {
			return
		}

		const epoch = ++historyEpoch
		historyLoading.value = true
		historyError.value = null
		try {
			const versions = await api.versions.list(asset.id)
			if (epoch !== historyEpoch || historyAsset.value?.id !== asset.id) {
				return
			}
			historyVersions.value = versions
		} catch (errorValue) {
			if (epoch === historyEpoch) {
				historyError.value = messageFor(errorValue)
			}
		} finally {
			if (epoch === historyEpoch) {
				historyLoading.value = false
			}
		}
	}

	function closeHistory(): void {
		historyEpoch += 1
		historyAsset.value = null
		historyVersions.value = []
		historyPreview.value = null
		historyError.value = null
		historyLoading.value = false
		historyBusy.value = false
		historyPreviewLoading.value = false
	}

	async function checkInCurrentRevision(asset: Asset, message: string): Promise<boolean> {
		if (historyBusy.value) {
			return false
		}

		historyBusy.value = true
		historyError.value = null
		try {
			await api.versions.checkIn(asset.id, message.trim() === '' ? null : message.trim())
			await loadHistory(asset)
			await loadAssets(asset.chapterId)
			return true
		} catch (errorValue) {
			historyError.value = messageFor(errorValue)
			return false
		} finally {
			historyBusy.value = false
		}
	}

	async function restoreVersion(asset: Asset, revisionId: string): Promise<boolean> {
		if (historyBusy.value) {
			return false
		}

		historyBusy.value = true
		historyError.value = null
		try {
			await api.versions.restore(asset.id, revisionId)
			historyPreview.value = null
			await loadHistory(asset)
			await loadAssets(asset.chapterId)
			return true
		} catch (errorValue) {
			historyError.value = messageFor(errorValue)
			return false
		} finally {
			historyBusy.value = false
		}
	}

	async function previewVersion(asset: Asset, revisionId: string): Promise<void> {
		if (historyPreview.value?.revisionId === revisionId) {
			historyPreview.value = null
			return
		}

		historyPreviewLoading.value = true
		historyError.value = null
		try {
			const result = await api.versions.content(asset.id, revisionId)
			historyPreview.value = {
				revisionId: result.revisionId,
				content: result.content,
			}
		} catch (errorValue) {
			historyError.value = messageFor(errorValue)
		} finally {
			historyPreviewLoading.value = false
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

	function toggleAssets(chapterId: number, current: boolean): void {
		expandedAssetChapterIds.value = current
			? {}
			: { [chapterId]: true }
	}

	async function initialize(): Promise<void> {
		await Promise.all([loadCapabilities(), loadBooks()])
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
			if (historyAsset.value?.id === asset.id) {
				closeHistory()
			}
			await loadAssets(asset.chapterId)
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	function openCompose(): void {
		if (selectedBookId.value === null) {
			return
		}

		composeOpen.value = true
		compositionsOpen.value = false
		structureManagerOpen.value = false
		tagsManagerOpen.value = false
		assetManagerOpen.value = false
		settingsOpen.value = false
		error.value = null
	}

	function closeCompose(): void {
		composeOpen.value = false
		error.value = null
	}

	function openCompositions(): void {
		if (selectedBookId.value === null) {
			return
		}

		compositionsOpen.value = true
		composeOpen.value = false
		structureManagerOpen.value = false
		tagsManagerOpen.value = false
		assetManagerOpen.value = false
		settingsOpen.value = false
		error.value = null
		loadCompositions()
	}

	function closeCompositions(): void {
		compositionsOpen.value = false
		compositionsEpoch += 1
		compositionsLoading.value = false
		error.value = null
	}

	async function loadCompositions(): Promise<void> {
		const bookId = selectedBookId.value
		if (bookId === null) {
			return
		}

		const epoch = ++compositionsEpoch
		compositionsLoading.value = true
		error.value = null
		try {
			const loaded = await api.compositions.list(bookId)
			if (epoch !== compositionsEpoch || selectedBookId.value !== bookId) {
				return
			}
			compositions.value = loaded
		} catch (errorValue) {
			if (epoch !== compositionsEpoch || selectedBookId.value !== bookId) {
				return
			}
			compositions.value = []
			error.value = messageFor(errorValue)
		} finally {
			if (epoch === compositionsEpoch && selectedBookId.value === bookId) {
				compositionsLoading.value = false
			}
		}
	}

	async function createComposition(structureId: number, name?: string): Promise<boolean> {
		const bookId = selectedBookId.value
		if (bookId === null || loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.compositions.create(bookId, structureId, name)
			if (selectedBookId.value !== bookId) {
				return true
			}
			composeOpen.value = false
			compositionsOpen.value = true
			await loadCompositions()
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	function replaceAsset(asset: Asset): void {
		assetsByChapter.value = {
			...assetsByChapter.value,
			[asset.chapterId]: (assetsByChapter.value[asset.chapterId] ?? [])
				.map((candidate) => candidate.id === asset.id ? asset : candidate),
		}
	}

	async function setAssetIncluded(asset: Asset, includeInComposition: boolean): Promise<boolean> {
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		const optimistic = { ...asset, includeInComposition }
		replaceAsset(optimistic)
		try {
			const updated = await api.assets.setIncludeInComposition(asset.id, includeInComposition)
			replaceAsset(updated)
			return true
		} catch (errorValue) {
			replaceAsset(asset)
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function retryComposition(composition: Composition): Promise<boolean> {
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.compositions.retry(composition.id)
			await loadCompositions()
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function deleteComposition(composition: Composition): Promise<boolean> {
		if (loading.mutation) {
			return false
		}

		loading.mutation = true
		error.value = null
		try {
			await api.compositions.remove(composition.id)
			await loadCompositions()
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		} finally {
			loading.mutation = false
		}
	}

	async function downloadComposition(composition: Composition): Promise<boolean> {
		error.value = null
		try {
			const blob = await api.compositions.download(composition.id)
			saveBlob(blob, compositionFileName(composition))
			return true
		} catch (errorValue) {
			error.value = messageFor(errorValue)
			return false
		}
	}

	function openSettings(): void {
		structureManagerOpen.value = false
		tagsManagerOpen.value = false
		assetManagerOpen.value = false
		composeOpen.value = false
		compositionsOpen.value = false
		settingsOpen.value = true
		error.value = null
	}

	function closeSettings(): void {
		settingsOpen.value = false
		error.value = null
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
		assetManagerOpen,
		assetsByChapter,
		books,
		composeOpen,
		compositions,
		compositionsLoading,
		compositionsOpen,
		capabilities,
		chapters,
		dialog,
		historyAsset,
		historyBusy,
		historyError,
		historyLoading,
		historyPreview,
		historyPreviewLoading,
		historyVersions,
		editorChapters,
		editorEntries,
		editorLoading,
		editorStructureId,
		error,
		expandedAssetChapterIds,
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
		settingsOpen,
		structures,
		structureManagerOpen,
		tags,
		tagsLoading,
		tagsManagerOpen,
		assignAssetTags,
		checkInCurrentRevision,
		clearError,
		closeAssetManager,
		closeCompose,
		closeCompositions,
		closeDialog,
		closeHistory,
		closeSettings,
		closeStructureManager,
		closeTagsManager,
		createAsset,
		createBook,
		createChapter,
		createComposition,
		createStructure,
		createTag,
		deleteAsset,
		deleteChapter,
		deleteComposition,
		deleteStructure,
		deleteTag,
		downloadComposition,
		initialize,
		loadAssets,
		loadBooks,
		loadCompositions,
		loadHistory,
		loadStructureEditor,
		loadTags,
		loadTree,
		moveChapter,
		openAssetManager,
		openCompose,
		openCompositions,
		openDialog,
		openHistory,
		openSettings,
		openStructureManager,
		openTagsManager,
		previewVersion,
		renameChapter,
		renameStructure,
		renameTag,
		restoreVersion,
		retryComposition,
		saveStructureEntries,
		selectBook,
		selectChapter,
		selectStructure,
		setAssetIncluded,
		toggleAssets,
		toggleChapter,
		uploadAsset,
	}
}

export type LibraryStore = ReturnType<typeof createLibraryStore>

const library = createLibraryStore()

export function useLibrary(): LibraryStore {
	return library
}
