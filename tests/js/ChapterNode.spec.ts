import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import ChapterNode from '../../src/components/ChapterNode.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Asset, Chapter, Tag } from '../../src/types'

const NcActionsStub = {
	template: '<div><slot /></div>',
}

const NcActionButtonStub = {
	emits: ['click'],
	template: '<button @click="$emit(\'click\')"><slot /></button>',
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
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function mountNode() {
	return mount(ChapterNode, {
		props: { chapter: chapter(), depth: 0 },
		global: {
			stubs: {
				NcActionButton: NcActionButtonStub,
				NcActionSeparator: true,
				NcActions: NcActionsStub,
				NcIconSvgWrapper: true,
				NcLoadingIcon: true,
			},
		},
	})
}

beforeEach(() => {
	const store = useLibrary()
	store.capabilities.value = { editor: 'text', versions: true }
	store.loading.mutation = false
	store.expandedChapterIds.value = {}
	store.expandedAssetChapterIds.value = {}
	store.compositionRules.value = []
	store.assetsByChapter.value = {
		1: [
			asset(10, [tag(1, 'Raw', '#2563eb'), tag(2, 'Summary', '#15803d')]),
			asset(11, []),
		],
	}
	store.loadingAssets.value = {}
})

describe('ChapterNode asset summary', () => {
	it('shows the asset and tag counts instead of inline chips', () => {
		const wrapper = mountNode()

		expect(wrapper.find('.chapter-asset-summary').exists()).toBe(true)
		expect(wrapper.findAll('.asset-chip')).toHaveLength(0)
		expect(wrapper.findAll('.summary-tag-dot')).toHaveLength(2)
		expect(wrapper.get('.chapter-asset-summary').text()).toContain('2')
	})

	it('toggles the asset table and keeps a single table open', async () => {
		const store = useLibrary()
		const wrapper = mountNode()

		await wrapper.get('.chapter-asset-summary').trigger('click')
		expect(store.expandedAssetChapterIds.value).toEqual({ 1: true })

		await wrapper.get('.chapter-asset-summary').trigger('click')
		expect(store.expandedAssetChapterIds.value).toEqual({})
	})
})

describe('ChapterNode asset table', () => {
	it('renders one row per asset with the full file name and named tags', () => {
		const store = useLibrary()
		store.expandedAssetChapterIds.value = { 1: true }
		const wrapper = mountNode()

		const rows = wrapper.findAll('.chapter-asset-table tbody tr')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('Asset 10')
		expect(rows[0].text()).toContain('Asset 10.md')
		expect(rows[0].text()).toContain('Raw')
		expect(rows[0].text()).toContain('Summary')
		expect(rows[1].findAll('.asset-tag')).toHaveLength(0)
	})

	it('marks the asset picked by the highest-priority matching rule', () => {
		const store = useLibrary()
		store.expandedAssetChapterIds.value = { 1: true }
		store.compositionRules.value = [
			{ tagId: 2, priority: 1, fallback: false },
			{ tagId: 1, priority: 2, fallback: false },
		]
		const wrapper = mountNode()

		const rows = wrapper.findAll('.chapter-asset-table tbody tr')
		expect(rows[0].text()).toContain('Rule 1')
		expect(rows[0].findAll('.asset-tag').at(-1)?.text()).toBe('Summary')
		expect(wrapper.get('.chapter-asset-summary').text()).toContain('1')
	})
})
