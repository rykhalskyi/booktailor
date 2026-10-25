import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
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
	const store = useLibrary()
	store.loading.mutation = false
	store.structureManagerOpen.value = false
	vi.restoreAllMocks()
})

describe('LibraryToolbar', () => {
	it('disables adding chapters while a mutation is running', () => {
		useLibrary().loading.mutation = true

		const wrapper = mountToolbar()
		const button = wrapper.get('button')

		expect(button.attributes('disabled')).toBeDefined()
	})

	it('opens the structure manager', async () => {
		const store = useLibrary()
		const open = vi.spyOn(store, 'openStructureManager').mockImplementation(() => {})

		const wrapper = mountToolbar()
		await wrapper.get('button').trigger('click')

		expect(open).toHaveBeenCalledOnce()
	})
})
