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
			chapters: {
				tree: vi.fn(),
			},
			assets: {
				list: vi.fn(),
			},
			compositions: {
				list: vi.fn(),
				create: vi.fn(),
				download: vi.fn(),
				remove: vi.fn(),
				retry: vi.fn(),
			},
		},
	}
})

import { api } from '../../src/api/client'
import ComposeScreen from '../../src/components/ComposeScreen.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Asset, Chapter, Composition, Structure } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcSelectStub = {
	props: ['modelValue', 'options', 'label', 'inputLabel', 'placeholder', 'disabled', 'clearable', 'searchable'],
	emits: ['update:modelValue'],
	template: `
		<select :disabled="disabled" @change="$emit('update:modelValue', options[Number($event.target.value)])">
			<option value="">-</option>
			<option v-for="(option, index) in options" :key="option.id ?? index" :value="index">{{ option.name }}</option>
		</select>`,
}

const NcTextFieldStub = {
	props: ['modelValue', 'label', 'disabled'],
	emits: ['update:modelValue'],
	template: '<input class="text-stub" type="text" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note" :data-type="type">{{ text }}</div>',
}

const NcLoadingIconStub = {
	props: ['name', 'size'],
	template: '<span class="loading-icon" />',
}

function structure(id: number, name: string): Structure {
	return {
		id,
		bookId: 1,
		name,
		isDefault: id === 10,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function composition(): Composition {
	return {
		id: 9,
		bookId: 1,
		structureId: 10,
		structureName: 'Default',
		outputName: 'Composition-2026-01-01-00-00-00',
		status: 'queued',
		outputFileId: null,
		outputPath: null,
		error: null,
		stale: false,
		createdAt: '2026-01-01T00:00:00+00:00',
		startedAt: null,
		completedAt: null,
	}
}

function chapter(id: number, name: string, number: string | null = null, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId: 1,
		parentId: null,
		name,
		fileId: id,
		number,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function asset(id: number, chapterId: number, name: string, includeInComposition: boolean): Asset {
	return {
		id,
		bookId: 1,
		chapterId,
		name,
		fileName: `${name}.md`,
		fileId: id,
		path: `BookTailor/Book/Chapter/${name}.md`,
		includeInComposition,
		tags: [],
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function resetStore(): void {
	const store = useLibrary()
	store.selectedBookId.value = 1
	store.selectedStructureId.value = 10
	store.structures.value = [structure(10, 'Default'), structure(20, 'Study')]
	store.composeOpen.value = true
	store.compositionsOpen.value = false
	store.compositions.value = []
	store.compositionsLoading.value = false
	store.loading.mutation = false
	store.error.value = null
}

function mountScreen() {
	return mount(ComposeScreen, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcLoadingIcon: NcLoadingIconStub,
				NcNoteCard: NcNoteCardStub,
				NcSelect: NcSelectStub,
				NcTextField: NcTextFieldStub,
			},
		},
	})
}

function button(wrapper: ReturnType<typeof mountScreen>, text: string) {
	return wrapper.findAll('button').find((candidate) => candidate.text().trim() === text)
}

beforeEach(() => {
	vi.clearAllMocks()
	resetStore()
	vi.mocked(api.chapters.tree).mockResolvedValue([])
	vi.mocked(api.assets.list).mockResolvedValue([])
	vi.mocked(api.compositions.list).mockResolvedValue([])
	vi.mocked(api.compositions.create).mockResolvedValue(composition())
})

describe('ComposeScreen', () => {
	it('creates a composition with the generated default name', async () => {
		const wrapper = mountScreen()

		expect((wrapper.get('.text-stub').element as HTMLInputElement).value)
			.toMatch(/^Composition-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/)
		expect(wrapper.find('.note[data-type="info"]').text()).toContain('checking them')
		await button(wrapper, 'Create composition')?.trigger('click')
		await flushPromises()

		expect(api.compositions.create).toHaveBeenCalledWith(
			1,
			10,
			expect.stringMatching(/^Composition-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/),
		)
		expect(useLibrary().compositionsOpen.value).toBe(true)
	})

	it('submits the chosen structure and composition name', async () => {
		const wrapper = mountScreen()

		await wrapper.get('select').setValue('1')
		await wrapper.get('.text-stub').setValue('Study Notes')

		await button(wrapper, 'Create composition')?.trigger('click')
		await flushPromises()

		expect(api.compositions.create).toHaveBeenCalledWith(1, 20, 'Study Notes')
	})

	it('requires a non-empty composition name', async () => {
		const wrapper = mountScreen()

		await wrapper.get('.text-stub').setValue('   ')

		expect(button(wrapper, 'Create composition')?.attributes('disabled')).toBeDefined()
		await button(wrapper, 'Create composition')?.trigger('click')
		expect(api.compositions.create).not.toHaveBeenCalled()
	})

	it('disables creation while a mutation is running', async () => {
		const wrapper = mountScreen()
		useLibrary().loading.mutation = true
		await wrapper.vm.$nextTick()

		expect(button(wrapper, 'Create composition')?.attributes('disabled')).toBeDefined()
		await button(wrapper, 'Create composition')?.trigger('click')
		expect(api.compositions.create).not.toHaveBeenCalled()
	})

	it('lists only selected assets from chapters included in the structure', async () => {
		const child = chapter(12, 'Details', '1.1')
		vi.mocked(api.chapters.tree).mockResolvedValue([
			chapter(11, 'Introduction', '1', [child]),
			chapter(13, 'Appendix', '2'),
		])
		vi.mocked(api.assets.list).mockImplementation(async (chapterId) => {
			if (chapterId === 11) {
				return [
					asset(101, 11, 'Preface', true),
					asset(102, 11, 'Draft notes', false),
				]
			}
			if (chapterId === 12) {
				return [asset(103, 12, 'Background', true)]
			}

			return [asset(104, 13, 'Unused appendix', false)]
		})

		const wrapper = mountScreen()
		await flushPromises()

		const groups = wrapper.findAll('.preview-chapter')
		expect(groups).toHaveLength(2)
		expect(groups[0].text()).toContain('Introduction')
		expect(groups[0].text()).toContain('Preface')
		expect(groups[0].text()).toContain('1')
		expect(groups[1].text()).toContain('Details')
		expect(groups[1].text()).toContain('Background')
		expect(wrapper.text()).toContain('2 assets from 2 chapters')
		expect(wrapper.text()).not.toContain('Draft notes')
		expect(wrapper.text()).not.toContain('Unused appendix')
		expect(api.chapters.tree).toHaveBeenCalledWith(1, 10)
		expect(api.assets.list).toHaveBeenCalledWith(11)
		expect(api.assets.list).toHaveBeenCalledWith(12)
		expect(api.assets.list).toHaveBeenCalledWith(13)
	})

	it('reloads the preview when the structure changes', async () => {
		vi.mocked(api.chapters.tree).mockImplementation(async (_bookId, structureId) => (
			structureId === 10
				? [chapter(11, 'Default chapter', '1')]
				: [chapter(21, 'Study chapter', '1')]
		))
		vi.mocked(api.assets.list).mockImplementation(async (chapterId) => (
			chapterId === 11
				? [asset(101, 11, 'Default asset', true)]
				: [asset(201, 21, 'Study asset', true)]
		))

		const wrapper = mountScreen()
		await flushPromises()
		expect(wrapper.text()).toContain('Default asset')

		await wrapper.get('select').setValue('1')
		await flushPromises()

		expect(api.chapters.tree).toHaveBeenCalledWith(1, 20)
		expect(wrapper.text()).toContain('Study asset')
		expect(wrapper.text()).not.toContain('Default asset')
	})

	it('shows an empty state when the structure has no selected assets', async () => {
		vi.mocked(api.chapters.tree).mockResolvedValue([chapter(11, 'Introduction', '1')])
		vi.mocked(api.assets.list).mockResolvedValue([asset(101, 11, 'Draft notes', false)])

		const wrapper = mountScreen()
		await flushPromises()

		expect(wrapper.get('.preview-empty').text()).toContain('No assets are marked')
	})
})
