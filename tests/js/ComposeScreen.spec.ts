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
import type { Composition, Structure, Tag } from '../../src/types'

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

function tag(id: number, name: string): Tag {
	return {
		id,
		bookId: 1,
		name,
		slug: name.toLowerCase(),
		color: '#2563eb',
	}
}

function composition(): Composition {
	return {
		id: 9,
		bookId: 1,
		structureId: 10,
		structureName: 'Default',
		status: 'queued',
		rules: [],
		outputFileId: null,
		outputPath: null,
		error: null,
		stale: false,
		createdAt: '2026-01-01T00:00:00+00:00',
		startedAt: null,
		completedAt: null,
	}
}

function resetStore(): void {
	const store = useLibrary()
	store.selectedBookId.value = 1
	store.selectedStructureId.value = 10
	store.compositionRules.value = []
	store.structures.value = [structure(10, 'Default'), structure(20, 'Study')]
	store.tags.value = [tag(11, 'Summary'), tag(22, 'Raw')]
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
	vi.mocked(api.compositions.list).mockResolvedValue([])
	vi.mocked(api.compositions.create).mockResolvedValue(composition())
})

describe('ComposeScreen', () => {
	it('submits an empty rule list to compose every asset', async () => {
		const wrapper = mountScreen()

		expect(wrapper.text()).toContain('No rules yet')
		await button(wrapper, 'Create composition')?.trigger('click')
		await flushPromises()

		expect(api.compositions.create).toHaveBeenCalledWith(1, 10, [], undefined)
		expect(useLibrary().compositionsOpen.value).toBe(true)
	})

	it('submits ordered rules with fallback and an output name', async () => {
		const wrapper = mountScreen()

		await button(wrapper, 'Add rule')?.trigger('click')
		await button(wrapper, 'Add rule')?.trigger('click')

		const selects = wrapper.findAll('select')
		expect(selects).toHaveLength(3)
		await selects[1].setValue('0')
		await selects[2].setValue('1')
		await wrapper.findAll('input[type="checkbox"]')[1].setValue(true)
		await wrapper.get('.text-stub').setValue('Study Notes')

		await button(wrapper, 'Create composition')?.trigger('click')
		await flushPromises()

		expect(api.compositions.create).toHaveBeenCalledWith(1, 10, [
			{ tagId: 11, priority: 1, fallback: false },
			{ tagId: 22, priority: 2, fallback: true },
		], 'Study Notes')
	})

	it('blocks duplicate tags before submitting', async () => {
		const wrapper = mountScreen()

		await button(wrapper, 'Add rule')?.trigger('click')
		await button(wrapper, 'Add rule')?.trigger('click')
		const selects = wrapper.findAll('select')
		await selects[1].setValue('0')
		await selects[2].setValue('0')

		expect(wrapper.text()).toContain('Each tag may appear only once')
		expect(button(wrapper, 'Create composition')?.attributes('disabled')).toBeDefined()

		await button(wrapper, 'Create composition')?.trigger('click')
		expect(api.compositions.create).not.toHaveBeenCalled()
	})

	it('moves and removes rules', async () => {
		const wrapper = mountScreen()

		await button(wrapper, 'Add rule')?.trigger('click')
		await button(wrapper, 'Add rule')?.trigger('click')
		let selects = wrapper.findAll('select')
		expect(selects).toHaveLength(3)

		await wrapper.findAll('button').filter((candidate) => candidate.text().trim() === 'Down')[0].trigger('click')
		selects = wrapper.findAll('select')
		await selects[1].setValue('1')
		await selects[2].setValue('0')

		await button(wrapper, 'Create composition')?.trigger('click')
		await flushPromises()

		expect(api.compositions.create).toHaveBeenCalledWith(1, 10, [
			{ tagId: 22, priority: 1, fallback: false },
			{ tagId: 11, priority: 2, fallback: false },
		], undefined)

		await button(wrapper, 'Remove')?.trigger('click')
		expect(wrapper.findAll('select')).toHaveLength(2)
	})
})
