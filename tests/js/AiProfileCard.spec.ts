import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AiProfileCard from '../../src/components/settings/AiProfileCard.vue'
import type { AiProfile } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcCheckboxRadioSwitchStub = {
	props: ['modelValue', 'type', 'name', 'disabled'],
	emits: ['update:modelValue'],
	template: '<button class="radio" @click="$emit(\'update:modelValue\', true)" />',
}

function profile(): AiProfile {
	return {
		id: 3,
		name: 'DeepSeek',
		provider: 'deepseek',
		apiKeyMasked: '••••••••1234',
		model: 'deepseek-chat',
		connectTimeoutSeconds: 30,
		readTimeoutSeconds: 60,
		maxTokens: 2048,
		isActive: true,
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: null,
	}
}

function mountCard() {
	return mount(AiProfileCard, {
		props: { profile: profile() },
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcCheckboxRadioSwitch: NcCheckboxRadioSwitchStub,
			},
		},
	})
}

describe('AiProfileCard', () => {
	it('shows the provider, model, and masked key', () => {
		const wrapper = mountCard()

		expect(wrapper.text()).toContain('DeepSeek')
		expect(wrapper.text()).toContain('deepseek-chat')
		expect(wrapper.text()).toContain('••••••••1234')
		expect(wrapper.text()).toContain('Active')
	})

	it('emits select and edit events', async () => {
		const wrapper = mountCard()

		await wrapper.get('.radio').trigger('click')
		expect(wrapper.emitted('select')).toHaveLength(1)

		const edit = wrapper.findAll('button').find((button) => button.text() === 'Edit')
		await edit?.trigger('click')
		expect(wrapper.emitted('edit')).toHaveLength(1)
	})
})
