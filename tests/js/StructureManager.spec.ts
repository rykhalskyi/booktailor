import { flushPromises, mount } from '@vue/test-utils'
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
			books: { list: vi.fn(), create: vi.fn() },
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
import StructureManager from '../../src/components/StructureManager.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Chapter, Structure, StructureEntry } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'label', 'clearable', 'searchable'],
	emits: ['update:modelValue'],
	template: '<div class="select-stub" />',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note">{{ text }}</div>',
}

function structure(id: number, bookId: number, isDefault = false): Structure {
	return {
		id,
		bookId,
		name: `Structure ${id}`,
		isDefault,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

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

function entry(chapterId: number, included: boolean, position: number): StructureEntry {
	return { chapterId, included, position, effectiveParentId: null }
}

function mountManager() {
	return mount(StructureManager, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcSelect: NcSelectStub,
				NcNoteCard: NcNoteCardStub,
				NcLoadingIcon: true,
				ConfirmDialog: true,
				TextInputDialog: true,
				MoveChapterDialog: true,
			},
		},
	})
}

function resetStore(): void {
	const store = useLibrary()
	store.selectedBookId.value = null
	store.selectedStructureId.value = null
	store.selectedChapterId.value = null
	store.structures.value = []
	store.chapters.value = []
	store.editorChapters.value = []
	store.editorEntries.value = []
	store.editorStructureId.value = null
	store.editorLoading.chapters = false
	store.editorLoading.entries = false
	store.loading.mutation = false
	store.error.value = null
	store.structureManagerOpen.value = true
	store.assetsByChapter.value = {}
	store.loadingAssets.value = {}
}

beforeEach(() => {
	vi.clearAllMocks()
	resetStore()
	vi.mocked(api.structures.entries).mockResolvedValue([])
	vi.mocked(api.chapters.tree).mockResolvedValue([])
	vi.mocked(api.structures.replaceEntries).mockImplementation(async (_structureId, entries) => entries)
})

describe('StructureManager', () => {
	it('loads entries and persists inclusion changes', async () => {
		const store = useLibrary()
		const child = chapter(2, 1, 'Child')
		const root = chapter(1, null, 'Root', [child])
		const numbered = { ...root, number: '1.', children: [{ ...child, number: '1.1' }] }
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		store.structures.value = [structure(10, 1)]
		vi.mocked(api.chapters.tree).mockImplementation(async (_bookId, structureId) => (
			structureId === undefined ? [root] : [numbered]
		))
		vi.mocked(api.structures.entries).mockResolvedValue([
			entry(1, true, 1),
			entry(2, true, 1),
		])

		const wrapper = mountManager()
		await flushPromises()

		const toggles = wrapper.findAll('input[type="checkbox"]')
		expect(toggles).toHaveLength(2)
		expect((toggles[0].element as HTMLInputElement).checked).toBe(true)

		await toggles[1].setValue(false)
		await flushPromises()

		expect(api.structures.replaceEntries).toHaveBeenCalledOnce()
		const payload = vi.mocked(api.structures.replaceEntries).mock.calls[0][1]
		expect(payload.find((item) => item.chapterId === 2)?.included).toBe(false)
	})

	it('disables inclusion toggles for the default structure', async () => {
		const store = useLibrary()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		store.structures.value = [structure(10, 1, true)]
		vi.mocked(api.chapters.tree).mockResolvedValue([chapter(1, null, 'Root')])
		vi.mocked(api.structures.entries).mockResolvedValue([entry(1, true, 1)])

		const wrapper = mountManager()
		await flushPromises()

		const toggle = wrapper.get('input[type="checkbox"]')
		expect(toggle.attributes('disabled')).toBeDefined()
	})

	it('shows a retry state when the editor cannot load', async () => {
		const store = useLibrary()
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		store.structures.value = [structure(10, 1)]
		vi.mocked(api.chapters.tree).mockRejectedValue(new Error('offline'))
		vi.mocked(api.structures.entries).mockRejectedValue(new Error('offline'))

		const wrapper = mountManager()
		await flushPromises()

		expect(wrapper.text()).toContain('could not be loaded')
		expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)
		expect(store.editorStructureId.value).toBeNull()
	})

	it('reorders canonical siblings within the selected structure', async () => {
		const store = useLibrary()
		const rootA = chapter(1, null, 'Root A')
		const rootB = chapter(2, null, 'Root B')
		store.selectedBookId.value = 1
		store.selectedStructureId.value = 10
		store.structures.value = [structure(10, 1)]
		vi.mocked(api.chapters.tree).mockResolvedValue([rootA, rootB])
		vi.mocked(api.structures.entries).mockResolvedValue([
			entry(1, true, 1),
			entry(2, true, 2),
		])

		const wrapper = mountManager()
		await flushPromises()

		const down = wrapper.findAll('button').find((button) => button.text() === 'Down')
		expect(down).toBeDefined()
		await down?.trigger('click')
		await flushPromises()

		expect(api.structures.replaceEntries).toHaveBeenCalledOnce()
		const payload = vi.mocked(api.structures.replaceEntries).mock.calls[0][1]
		expect(payload.find((item) => item.chapterId === 1)?.position).toBe(2)
		expect(payload.find((item) => item.chapterId === 2)?.position).toBe(1)
	})
})
