import { shallowMount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import App from '../../src/App.vue'

describe('App', () => {
	it('renders the app shell', () => {
		const wrapper = shallowMount(App, {
			global: { renderStubDefaultSlot: true },
		})

		expect(wrapper.text()).toContain('Hello world!')
	})
})
