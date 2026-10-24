import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ConfirmDialog from '../../src/components/ConfirmDialog.vue'

const NcDialogStub = {
	template: '<div><slot /><slot name="actions" /></div>',
}

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

function mountDialog() {
	return mount(ConfirmDialog, {
		props: {
			title: 'Delete chapter',
			message: 'Delete this chapter and its assets?',
			confirmLabel: 'Delete chapter',
		},
		global: {
			stubs: {
				NcDialog: NcDialogStub,
				NcButton: NcButtonStub,
				NcNoteCard: true,
			},
		},
	})
}

describe('ConfirmDialog', () => {
	it('does not confirm until the destructive action is clicked', async () => {
		const wrapper = mountDialog()
		expect(wrapper.emitted('confirm')).toBeUndefined()

		const buttons = wrapper.findAll('button')
		await buttons[buttons.length - 1].trigger('click')

		expect(wrapper.emitted('confirm')).toHaveLength(1)
	})

	it('emits close from the cancel button', async () => {
		const wrapper = mountDialog()

		await wrapper.findAll('button')[0].trigger('click')

		expect(wrapper.emitted('close')).toHaveLength(1)
		expect(wrapper.emitted('confirm')).toBeUndefined()
	})
})
