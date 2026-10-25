import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, describe, expect, it } from 'vitest'
import ChapterTree from '../../src/components/ChapterTree.vue'
import { useLibrary } from '../../src/stores/useLibrary'
import type { Chapter } from '../../src/types'

function chapter(id: number, parentId: number | null, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId: 1,
		parentId,
		name: `Chapter ${id}`,
		fileId: id,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

function mountTree() {
	return mount(ChapterTree, {
		attachTo: document.body,
		global: {
			stubs: {
				NcButton: true,
				NcEmptyContent: true,
				NcLoadingIcon: true,
				NcActions: true,
				NcActionButton: true,
				NcActionSeparator: true,
				NcIconSvgWrapper: true,
			},
		},
	})
}

const root = chapter(1, null, [chapter(2, 1)])

afterEach(() => {
	const store = useLibrary()
	store.chapters.value = []
	store.expandedChapterIds.value = {}
	store.selectedChapterId.value = null
	store.loading.mutation = false
	document.body.innerHTML = ''
})

describe('ChapterTree', () => {
	it('renders structure numbers when present', () => {
		const store = useLibrary()
		const child = { ...chapter(2, 1), number: '1.1' }
		const parent = { ...chapter(1, null, [child]), number: '1.' }
		store.chapters.value = [parent]

		const wrapper = mountTree()

		expect(wrapper.text()).toContain('1.')
		expect(wrapper.text()).toContain('1.1')
		wrapper.unmount()
	})

	it('renders ARIA tree semantics', () => {
		const store = useLibrary()
		store.chapters.value = [root]

		const wrapper = mountTree()
		const tree = wrapper.get('[role="tree"]')
		const items = wrapper.findAll('[role="treeitem"]')

		expect(tree.attributes('aria-label')).toBe('Chapters')
		expect(items).toHaveLength(2)
		expect(items[0].attributes('aria-level')).toBe('1')
		expect(items[0].attributes('aria-expanded')).toBe('true')
		expect(items[0].attributes('aria-selected')).toBe('false')
		expect(items[1].attributes('aria-level')).toBe('2')

		wrapper.unmount()
	})

	it('supports arrow-key expansion and focus movement', async () => {
		const store = useLibrary()
		store.chapters.value = [root]
		store.expandedChapterIds.value = { [root.id]: false }

		const wrapper = mountTree()
		expect(wrapper.findAll('.chapter-name')).toHaveLength(1)

		await wrapper.get('.chapter-name').trigger('keydown', { key: 'ArrowRight' })
		await nextTick()

		expect(store.expandedChapterIds.value[root.id]).toBe(true)
		expect(wrapper.findAll('.chapter-name')).toHaveLength(2)

		await wrapper.get('.chapter-name').trigger('keydown', { key: 'ArrowRight' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 2')

		await wrapper.findAll('.chapter-name')[1].trigger('keydown', { key: 'ArrowLeft' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 1')

		await wrapper.get('.chapter-name').trigger('keydown', { key: 'ArrowLeft' })
		await nextTick()

		expect(store.expandedChapterIds.value[root.id]).toBe(false)
		expect(wrapper.findAll('.chapter-name')).toHaveLength(1)

		wrapper.unmount()
	})

	it('moves focus with Home, End, and vertical arrows', async () => {
		const store = useLibrary()
		store.chapters.value = [root]

		const wrapper = mountTree()
		const buttons = wrapper.findAll('.chapter-name')

		await buttons[0].trigger('keydown', { key: 'End' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 2')

		await buttons[1].trigger('keydown', { key: 'Home' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 1')

		await buttons[0].trigger('keydown', { key: 'ArrowDown' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 2')

		await buttons[1].trigger('keydown', { key: 'ArrowUp' })
		expect(document.activeElement?.textContent?.trim()).toBe('Chapter 1')

		wrapper.unmount()
	})
})
