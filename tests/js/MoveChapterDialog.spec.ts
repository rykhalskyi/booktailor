import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import type { Chapter } from '../../src/types'
import MoveChapterDialog from '../../src/components/MoveChapterDialog.vue'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcDialogStub = {
	props: ['open', 'name', 'size'],
	emits: ['closing'],
	template: '<div><slot /><slot name="actions" /></div>',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note">{{ text }}</div>',
}

const NcSelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'label', 'clearable', 'searchable', 'placeholder'],
	emits: ['update:modelValue'],
	template: '<div class="select-stub" />',
}

function chapter(id: number, parentId: number | null, name: string, children: Chapter[] = []): Chapter {
	return {
		id,
		bookId: 1,
		parentId,
		name,
		fileId: id,
		children,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

const childA1 = chapter(3, 1, 'Child A1')
const childA2 = chapter(4, 1, 'Child A2')
const rootA = chapter(1, null, 'Root A', [childA1, childA2])
const rootB = chapter(2, null, 'Root B')
const tree = [rootA, rootB]

function mountDialog(chapterValue: Chapter = childA2) {
	return mount(MoveChapterDialog, {
		props: {
			chapter: chapterValue,
			chapters: tree,
		},
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcDialog: NcDialogStub,
				NcNoteCard: NcNoteCardStub,
				NcSelect: NcSelectStub,
			},
		},
	})
}

describe('MoveChapterDialog', () => {
	it('requires an explicit target before moving', async () => {
		const wrapper = mountDialog()
		const buttons = wrapper.findAll('button')
		const confirm = buttons[1]

		expect(confirm.attributes('disabled')).toBeDefined()

		await wrapper.findComponent(NcSelectStub).vm.$emit('update:modelValue', { id: 2, label: 'Under “Root B”' })

		expect(confirm.attributes('disabled')).toBeUndefined()

		await confirm.trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([[2]])
	})

	it('emits a null parent when promoting to the book root', async () => {
		const wrapper = mountDialog()
		await wrapper.findComponent(NcSelectStub).vm.$emit('update:modelValue', { id: null, label: 'Book root' })

		await wrapper.findAll('button')[1].trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([[null]])
	})
})
