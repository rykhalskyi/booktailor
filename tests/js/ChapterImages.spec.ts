import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Chapter, ChapterImage } from '../../src/types'

const { apiMock, ApiErrorMock } = vi.hoisted(() => {
	class ApiError extends Error {
		constructor(message: string, public readonly status: number) {
			super(message)
			this.name = 'ApiError'
		}
	}

	return {
		ApiErrorMock: ApiError,
		apiMock: {
			images: {
				list: vi.fn(),
				upload: vi.fn(),
				replace: vi.fn(),
				remove: vi.fn(),
			},
		},
	}
})

vi.mock('../../src/api/client', () => ({
	ApiError: ApiErrorMock,
	api: apiMock,
}))

import ChapterImages from '../../src/components/ChapterImages.vue'
import { useLibrary } from '../../src/stores/useLibrary'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcDialogStub = {
	props: ['open', 'name'],
	emits: ['closing'],
	template: '<div class="dialog"><slot /><div class="dialog-actions"><slot name="actions" /></div></div>',
}

const NcEmptyContentStub = {
	props: ['name', 'description'],
	template: '<div class="empty">{{ name }}<slot name="action" /></div>',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note">{{ text }}</div>',
}

function chapter(): Chapter {
	return {
		id: 1,
		bookId: 1,
		parentId: null,
		name: 'Chapter',
		fileId: 1,
		children: [],
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function image(references = 0): ChapterImage {
	return {
		fileId: 9,
		name: 'cover.png',
		mime: 'image/png',
		size: 2048,
		path: 'images/cover.png',
		references,
	}
}

function mountImages() {
	return mount(ChapterImages, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcDialog: NcDialogStub,
				NcEmptyContent: NcEmptyContentStub,
				NcLoadingIcon: true,
				NcNoteCard: NcNoteCardStub,
			},
		},
	})
}

beforeEach(() => {
	const store = useLibrary()
	store.selectedBookId.value = 1
	store.selectedChapterId.value = 1
	store.chapters.value = [chapter()]

	apiMock.images.list.mockReset().mockResolvedValue([image()])
	apiMock.images.upload.mockReset().mockResolvedValue(image())
	apiMock.images.replace.mockReset().mockResolvedValue(image())
	apiMock.images.remove.mockReset().mockResolvedValue(undefined)
})

describe('ChapterImages', () => {
	it('renders the gallery and copies a Markdown snippet', async () => {
		const writeText = vi.fn().mockResolvedValue(undefined)
		Object.defineProperty(navigator, 'clipboard', {
			configurable: true,
			value: { writeText },
		})
		const wrapper = mountImages()
		await flushPromises()

		expect(wrapper.text()).toContain('cover.png')
		expect(wrapper.text()).toContain('Not referenced')

		const copy = wrapper.findAll('button').find((button) => button.text() === 'Copy snippet')
		await copy?.trigger('click')
		await flushPromises()

		expect(writeText).toHaveBeenCalledWith('![cover](images/cover.png)')
		expect(wrapper.text()).toContain('Copied')
	})

	it('uploads a selected image', async () => {
		const wrapper = mountImages()
		await flushPromises()

		const upload = wrapper.findAll('button').find((button) => button.text() === 'Upload image')
		await upload?.trigger('click')
		const input = wrapper.get('input[type="file"]')
		const file = new File(['png'], 'new.png', { type: 'image/png' })
		Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
		await input.trigger('change')
		await flushPromises()

		expect(apiMock.images.upload).toHaveBeenCalledWith(1, file)
	})

	it('replaces an existing image without changing its reference', async () => {
		const wrapper = mountImages()
		await flushPromises()

		const replace = wrapper.findAll('button').find((button) => button.text() === 'Replace')
		await replace?.trigger('click')
		const input = wrapper.get('input[type="file"]')
		const file = new File(['png'], 'cover.png', { type: 'image/png' })
		Object.defineProperty(input.element, 'files', { configurable: true, value: [file] })
		await input.trigger('change')
		await flushPromises()

		expect(apiMock.images.replace).toHaveBeenCalledWith(1, 9, file)
	})

	it('shows the server message when deletion is blocked by a reference', async () => {
		apiMock.images.remove.mockRejectedValue(new ApiErrorMock('Image is referenced by: Chapter text.md', 409))
		const wrapper = mountImages()
		await flushPromises()

		const remove = wrapper.findAll('button').find((button) => button.text() === 'Delete')
		await remove?.trigger('click')
		const confirm = wrapper.findAll('button').find((button) => button.text() === 'Delete image')
		await confirm?.trigger('click')
		await flushPromises()

		expect(wrapper.text()).toContain('Image is referenced by: Chapter text.md')
	})

	it('deletes an unreferenced image', async () => {
		const wrapper = mountImages()
		await flushPromises()

		const remove = wrapper.findAll('button').find((button) => button.text() === 'Delete')
		await remove?.trigger('click')
		const confirm = wrapper.findAll('button').find((button) => button.text() === 'Delete image')
		await confirm?.trigger('click')
		await flushPromises()

		expect(apiMock.images.remove).toHaveBeenCalledWith(1, 9)
		expect(wrapper.find('.dialog').exists()).toBe(false)
	})
})
