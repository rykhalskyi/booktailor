import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../src/api/client', () => ({
	ApiError: class extends Error {},
	api: {
		bookShares: {
			list: vi.fn(),
			create: vi.fn(),
			update: vi.fn(),
			remove: vi.fn(),
			search: vi.fn(),
		},
	},
}))

import { api } from '../../src/api/client'
import ShareBookDialog from '../../src/components/ShareBookDialog.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import { testBook } from './fixtures'

const NcDialogStub = {
	props: ['open', 'name'],
	emits: ['closing'],
	template: '<div class="dialog"><h2>{{ name }}</h2><slot /><div class="dialog-actions"><slot name="actions" /></div></div>',
}

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note">{{ text }}</div>',
}

function mountDialog() {
	return mount(ShareBookDialog, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcDialog: NcDialogStub,
				NcLoadingIcon: true,
				NcNoteCard: NcNoteCardStub,
				NcSelect: true,
				NcSelectUsers: true,
			},
		},
	})
}

function buttonByText(wrapper: ReturnType<typeof mountDialog>, text: string) {
	const button = wrapper.findAll('button').find((item) => item.text().trim() === text)
	if (button === undefined) {
		throw new Error(`Button ${text} not found`)
	}

	return button
}

const share = {
	id: 5,
	bookId: 1,
	userId: 'reader',
	displayName: 'Reader',
	role: 'read' as const,
	createdAt: '2026-01-01T00:00:00+00:00',
	updatedAt: '2026-01-01T00:00:00+00:00',
}

beforeEach(() => {
	vi.clearAllMocks()
	const store = useLibrary()
	store.books.value = [testBook()]
	store.selectedBookId.value = 1
	store.shareDialogOpen.value = true
	store.bookShares.value = [share]
	store.shareBusy.value = false
	store.shareError.value = null
	store.sharesLoading.value = false
	vi.mocked(api.bookShares.list).mockResolvedValue([share])
	vi.mocked(api.bookShares.remove).mockResolvedValue(undefined)
})

describe('ShareBookDialog', () => {
	it('lists current shares and revokes after confirmation', async () => {
		const wrapper = mountDialog()
		expect(wrapper.text()).toContain('Reader')
		expect(wrapper.text()).toContain('reader')

		await buttonByText(wrapper, 'Revoke').trigger('click')
		expect(wrapper.text()).toContain('Remove Reader from this book?')

		await buttonByText(wrapper, 'Revoke access').trigger('click')
		await flushPromises()

		expect(api.bookShares.remove).toHaveBeenCalledWith(1, 5)
	})

	it('changes a share role', async () => {
		const store = useLibrary()
		vi.mocked(api.bookShares.update).mockResolvedValue({ ...share, role: 'write' })

		expect(await store.updateBookShare(store.bookShares.value[0], 'write')).toBe(true)
		expect(api.bookShares.update).toHaveBeenCalledWith(1, 5, 'write')
	})
})
