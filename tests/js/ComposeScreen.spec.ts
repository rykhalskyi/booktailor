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
			compositions: {
				list: vi.fn(),
				create: vi.fn(),
				preview: vi.fn(),
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
	vi.mocked(api.compositions.preview).mockResolvedValue([
		{
			chapter: chapter(11, 'Chapter', '1'),
			assets: [asset(101, 11, 'Asset', true)],
		},
	])
	vi.mocked(api.compositions.list).mockResolvedValue([])
	vi.mocked(api.compositions.create).mockResolvedValue(composition())
})

describe('ComposeScreen', () => {
	it('creates a composition with the generated default name', async () => {
		const wrapper = mountScreen()
		await flushPromises()

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
		await flushPromises()

		await wrapper.get('select').setValue('1')
		await wrapper.get('.text-stub').setValue('Study Notes')
		await flushPromises()

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

	it('renders the preview groups returned for the structure', async () => {
		vi.mocked(api.compositions.preview).mockResolvedValue([
			{
				chapter: chapter(11, 'Introduction', '1'),
				assets: [asset(101, 11, 'Preface', true)],
			},
			{
				chapter: chapter(12, 'Details', '1.1'),
				assets: [asset(103, 12, 'Background', true)],
			},
		])

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
		expect(api.compositions.preview).toHaveBeenCalledWith(1, 10)
		expect(api.compositions.preview).toHaveBeenCalledTimes(1)
	})

	it('reloads the preview when the structure changes', async () => {
		vi.mocked(api.compositions.preview).mockImplementation(async (_bookId, structureId) => (
			structureId === 10
				? [{
					chapter: chapter(11, 'Default chapter', '1'),
					assets: [asset(101, 11, 'Default asset', true)],
				}]
				: [{
					chapter: chapter(21, 'Study chapter', '1'),
					assets: [asset(201, 21, 'Study asset', true)],
				}]
		))

		const wrapper = mountScreen()
		await flushPromises()
		expect(wrapper.text()).toContain('Default asset')

		await wrapper.get('select').setValue('1')
		await flushPromises()

		expect(api.compositions.preview).toHaveBeenCalledWith(1, 20)
		expect(wrapper.text()).toContain('Study asset')
		expect(wrapper.text()).not.toContain('Default asset')
	})

	it('blocks creation when the structure has no selected assets', async () => {
		vi.mocked(api.compositions.preview).mockResolvedValue([])

		const wrapper = mountScreen()
		await flushPromises()

		expect(wrapper.get('.preview-empty').text()).toContain('No assets are marked')
		expect(button(wrapper, 'Create composition')?.attributes('disabled')).toBeDefined()
	})
})
