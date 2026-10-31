import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import AssetList from '../../src/components/AssetList.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Asset, Chapter, Tag } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcActionsStub = {
	template: '<div class="actions"><slot /></div>',
}

const NcActionButtonStub = {
	emits: ['click'],
	template: '<button class="action" @click="$emit(\'click\')"><slot /></button>',
}

const NcEmptyContentStub = {
	props: ['name', 'description'],
	template: '<div class="empty"><slot name="action" /></div>',
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

function tag(id: number, name: string, color: string): Tag {
	return { id, bookId: 1, name, slug: name.toLowerCase(), color }
}

function asset(id: number, tags: Tag[]): Asset {
	return {
		id,
		bookId: 1,
		chapterId: 1,
		name: `Asset ${id}`,
		fileName: `Asset ${id}.md`,
		fileId: id,
		path: `BookTailor/Book/Chapter/Asset ${id}.md`,
		tags,
		includeInComposition: false,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function mountList(showBack = false) {
	return mount(AssetList, {
		props: { showBack },
		global: {
			stubs: {
				NcActionButton: NcActionButtonStub,
				NcActions: NcActionsStub,
				NcButton: NcButtonStub,
				NcEmptyContent: NcEmptyContentStub,
				NcLoadingIcon: true,
				NcSelect: true,
				ChapterImages: true,
			},
		},
	})
}

beforeEach(() => {
	const raw = tag(1, 'Raw', '#2563eb')
	const summary = tag(2, 'Summary', '#15803d')
	const store = useLibrary()
	store.selectedBookId.value = 1
	store.selectedChapterId.value = 1
	store.chapters.value = [chapter()]
	store.tags.value = [raw, summary]
	store.assetsByChapter.value = {
		1: [asset(10, [raw]), asset(11, [summary])],
	}
	store.loading.mutation = false
	store.dialog.value = null
})

describe('AssetList', () => {
	it('opens the tag assignment dialog from an asset row', async () => {
		const wrapper = mountList()

		const tagsButton = wrapper.findAll('button').find((button) => button.text().trim() === 'Tags')
		await tagsButton?.trigger('click')

		const store = useLibrary()
		expect(store.dialog.value).toEqual({ type: 'asset-tags', asset: expect.objectContaining({ id: 10 }) })
	})

	it('emits back when shown as a standalone asset manager', async () => {
		const wrapper = mountList(true)

		const back = wrapper.findAll('button').find((button) => button.text().trim() === 'Back to chapters')
		await back?.trigger('click')

		expect(wrapper.emitted('back')).toHaveLength(1)
	})

	it('filters the chapter assets by a tag chip', async () => {
		const wrapper = mountList()
		expect(wrapper.findAll('.asset-row')).toHaveLength(2)

		const rawChip = wrapper.findAll('.asset-tag').find((chip) => chip.text().trim() === 'Raw')
		await rawChip?.trigger('click')

		expect(wrapper.findAll('.asset-row')).toHaveLength(1)
		expect(wrapper.text()).toContain('Asset 10.md')
		expect(wrapper.text()).not.toContain('Asset 11.md')
	})
})
