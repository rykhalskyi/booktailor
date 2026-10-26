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
import CompositionsList from '../../src/components/CompositionsList.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Composition } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcLoadingIconStub = {
	props: ['name', 'size'],
	template: '<span class="loading-icon" />',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note">{{ text }}</div>',
}

const ConfirmDialogStub = {
	props: ['title', 'message', 'confirmLabel', 'busy', 'error'],
	emits: ['confirm', 'close'],
	template: '<div class="confirm-stub"><button class="confirm-delete" @click="$emit(\'confirm\')">Confirm</button></div>',
}

function composition(overrides: Partial<Composition> = {}): Composition {
	return {
		id: 9,
		bookId: 1,
		structureId: 10,
		structureName: 'Default',
		status: 'succeeded',
		rules: [],
		outputFileId: 100,
		outputPath: '/BookTailor/Book/compositions/default-20261026-120000.md',
		error: null,
		stale: false,
		createdAt: '2026-01-01T00:00:00+00:00',
		startedAt: null,
		completedAt: '2026-01-01T00:00:05+00:00',
		...overrides,
	}
}

function resetStore(): void {
	const store = useLibrary()
	store.selectedBookId.value = 1
	store.compositions.value = []
	store.compositionsLoading.value = false
	store.compositionsOpen.value = true
	store.composeOpen.value = false
	store.loading.mutation = false
	store.error.value = null
}

function mountList() {
	return mount(CompositionsList, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
							NcEmptyContent: {
					props: ['name', 'description'],
					template: '<div class="empty"><h2>{{ name }}</h2><p>{{ description }}</p><slot name="action" /></div>',
				},
				NcLoadingIcon: NcLoadingIconStub,
				NcNoteCard: NcNoteCardStub,
				ConfirmDialog: ConfirmDialogStub,
			},
		},
	})
}

beforeEach(() => {
	vi.clearAllMocks()
	resetStore()
})

describe('CompositionsList', () => {
	it('renders statuses and the actions allowed for each row', async () => {
		const rows = [
			composition({ id: 1, structureName: 'Succeeded', status: 'succeeded' }),
			composition({ id: 2, structureName: 'Failed', status: 'failed', outputFileId: null, outputPath: null, error: 'No assets matched the composition rules' }),
			composition({ id: 3, structureName: 'Stale', status: 'running', stale: true, outputFileId: null, outputPath: null }),
		]
		vi.mocked(api.compositions.list).mockResolvedValue(rows)

		const wrapper = mountList()
		await flushPromises()

		expect(wrapper.text()).toContain('Succeeded')
		expect(wrapper.text()).toContain('Failed')
		expect(wrapper.text()).toContain('Stale')
		expect(wrapper.text()).toContain('No assets matched the composition rules')
		expect(wrapper.findAll('button').filter((button) => button.text().trim() === 'Delete')).toHaveLength(2)
		expect(wrapper.findAll('button').filter((button) => button.text().trim() === 'Retry')).toHaveLength(2)
		expect(wrapper.findAll('button').filter((button) => button.text().trim() === 'Download')).toHaveLength(3)
	})

	it('shows a spinner only for a running composition', async () => {
		vi.mocked(api.compositions.list).mockResolvedValue([
			composition({ id: 1, status: 'running', stale: false, outputFileId: null, outputPath: null }),
			composition({ id: 2, status: 'queued', stale: false, outputFileId: null, outputPath: null }),
			composition({ id: 3, status: 'running', stale: true, outputFileId: null, outputPath: null }),
		])

		const wrapper = mountList()
		await flushPromises()

		expect(wrapper.findAll('.loading-icon')).toHaveLength(1)
	})

	it('retries a failed composition and reloads the list', async () => {
		const failed = composition({ id: 2, status: 'failed', outputFileId: null, outputPath: null })
		vi.mocked(api.compositions.list).mockResolvedValue([failed])
		vi.mocked(api.compositions.retry).mockResolvedValue({ ...failed, status: 'queued' })

		const wrapper = mountList()
		await flushPromises()

		const retry = wrapper.findAll('button').find((button) => button.text().trim() === 'Retry')
		await retry?.trigger('click')
		await flushPromises()

		expect(api.compositions.retry).toHaveBeenCalledWith(2)
		expect(api.compositions.list).toHaveBeenCalledTimes(2)
	})

	it('confirms before deleting a composition', async () => {
		vi.mocked(api.compositions.list).mockResolvedValue([composition()])
		vi.mocked(api.compositions.remove).mockResolvedValue()

		const wrapper = mountList()
		await flushPromises()

		const remove = wrapper.findAll('button').find((button) => button.text().trim() === 'Delete')
		await remove?.trigger('click')
		expect(wrapper.find('.confirm-stub').exists()).toBe(true)

		await wrapper.get('.confirm-delete').trigger('click')
		await flushPromises()

		expect(api.compositions.remove).toHaveBeenCalledWith(9)
		expect(api.compositions.list).toHaveBeenCalledTimes(2)
	})

	it('shows an empty state and opens the compose view', async () => {
		vi.mocked(api.compositions.list).mockResolvedValue([])

		const wrapper = mountList()
		await flushPromises()

		expect(wrapper.text()).toContain('No compositions yet')
		const create = wrapper.findAll('button').find((button) => button.text().trim() === 'Create composition')
		await create?.trigger('click')
		expect(useLibrary().composeOpen.value).toBe(true)
	})
})
