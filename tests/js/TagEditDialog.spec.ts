import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TagEditDialog from '../../src/components/TagEditDialog.vue'
import { TAG_COLORS } from '../../src/utils/tags'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcTextFieldStub = {
	props: ['modelValue', 'label', 'autofocus'],
	emits: ['update:modelValue'],
	template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

const NcDialogStub = {
	props: ['open', 'name', 'size'],
	emits: ['closing'],
	template: '<div><slot /><slot name="actions" /></div>',
}

function mountDialog(initialName = 'Raw') {
	return mount(TagEditDialog, {
		props: {
			title: 'New tag',
			initialName,
			initialColor: TAG_COLORS[0].value,
			confirmLabel: 'Create tag',
		},
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcDialog: NcDialogStub,
				NcNoteCard: true,
				NcTextField: NcTextFieldStub,
			},
		},
	})
}

describe('TagEditDialog', () => {
	it('renders the full palette and marks the initial color', () => {
		const wrapper = mountDialog()

		expect(wrapper.findAll('.tag-color-option')).toHaveLength(TAG_COLORS.length)
		expect(wrapper.findAll('.tag-color-option')[0].attributes('aria-pressed')).toBe('true')
	})

	it('emits the selected name and color', async () => {
		const wrapper = mountDialog()

		await wrapper.get('input').setValue('Source')
		await wrapper.findAll('.tag-color-option')[4].trigger('click')
		await wrapper.findAll('button').find((button) => button.text().trim() === 'Create tag')?.trigger('click')

		expect(wrapper.emitted('confirm')?.[0]).toEqual([
			{ name: 'Source', color: TAG_COLORS[4].value },
		])
	})

	it('disables confirmation for an empty name', () => {
		const wrapper = mountDialog('')

		expect(wrapper.findAll('button').find((button) => button.text().trim() === 'Create tag')?.attributes('disabled')).toBeDefined()
	})
})
