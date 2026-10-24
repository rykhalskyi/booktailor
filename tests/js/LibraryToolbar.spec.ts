import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import LibraryToolbar from '../../src/components/LibraryToolbar.vue'
import { useLibrary } from '../../src/stores/useLibrary'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

function mountToolbar() {
	return mount(LibraryToolbar, {
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcSelect: true,
			},
		},
	})
}

afterEach(() => {
	useLibrary().loading.mutation = false
})

describe('LibraryToolbar', () => {
	it('disables adding chapters while a mutation is running', () => {
		useLibrary().loading.mutation = true

		const wrapper = mountToolbar()
		const button = wrapper.get('button')

		expect(button.attributes('disabled')).toBeDefined()
	})
})
