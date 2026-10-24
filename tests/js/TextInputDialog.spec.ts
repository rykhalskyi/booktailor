import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TextInputDialog from '../../src/components/TextInputDialog.vue'

const NcDialogStub = {
	template: '<div><slot /><slot name="actions" /></div>',
}

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcTextFieldStub = {
	props: ['label', 'modelValue'],
	emits: ['update:modelValue'],
	template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
}

function mountDialog() {
	return mount(TextInputDialog, {
		props: {
			title: 'Rename chapter',
			label: 'Chapter name',
			initialValue: 'Chapter',
			confirmLabel: 'Rename chapter',
		},
		global: {
			stubs: {
				NcDialog: NcDialogStub,
				NcButton: NcButtonStub,
				NcNoteCard: true,
				NcTextField: NcTextFieldStub,
			},
		},
	})
}

describe('TextInputDialog', () => {
	it('trims the value and emits confirm once on form submit', async () => {
		const wrapper = mountDialog()

		await wrapper.find('input').setValue('  Renamed chapter  ')
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('confirm')).toEqual([['Renamed chapter']])
	})

	it('does not confirm twice when Enter is followed by a form submit', async () => {
		const wrapper = mountDialog()

		await wrapper.find('input').setValue('Renamed chapter')
		await wrapper.find('input').trigger('keyup.enter')
		await wrapper.find('form').trigger('submit')

		expect(wrapper.emitted('confirm')).toEqual([['Renamed chapter']])
	})
})
