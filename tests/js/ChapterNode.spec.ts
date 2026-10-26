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
	store.assetsByChapter.value = {
		1: [
			asset(10, [tag(1, 'Raw', '#2563eb'), tag(2, 'Summary', '#15803d')]),
			asset(11, []),
		],
	}
	store.loadingAssets.value = {}
})

describe('ChapterNode asset chips', () => {
	it('renders one colored dot per tag on an asset chip', () => {
		const wrapper = mountNode()

		expect(wrapper.findAll('.asset-chip')).toHaveLength(2)
		expect(wrapper.findAll('.asset-chip-tag')).toHaveLength(2)
		expect(wrapper.get('.asset-chip-tags').attributes('title')).toBe('Raw, Summary')
	})

	it('does not render dots for an untagged asset', () => {
		const wrapper = mountNode()
		const untagged = wrapper.findAll('.asset-chip')[1]

		expect(untagged.findAll('.asset-chip-tag')).toHaveLength(0)
	})
})
