import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../src/api/client', () => {
	class ApiError extends Error {
		public readonly status: number

		public constructor(message: string, status: number) {
			super(message)
			this.name = 'ApiError'
			this.status = status
		}
	}

	return {
		ApiError,
		api: {
			ai: {
				actions: {
					confirm: vi.fn(),
					reject: vi.fn(),
				},
			},
			assets: {
				content: vi.fn(),
			},
		},
	}
})

import { api } from '../../src/api/client'
import AiProposalCard from '../../src/components/AiProposalCard.vue'
import type { AiAction } from '../../src/types'

const NcButtonStub = {
	props: ['variant', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const NcNoteCardStub = {
	props: ['type', 'text'],
	template: '<div class="note" :data-type="type">{{ text }}</div>',
}

const NcLoadingIconStub = {
	template: '<span class="loading" />',
}

function action(overrides: Partial<AiAction> = {}): AiAction {
	return {
		id: 9,
		userId: 'user',
		bookId: 1,
		action: 'create_asset',
		payload: {
			chapterId: 2,
			name: 'Summary',
			content: '# Summary',
			summary: 'Create a summary asset',
		},
		status: 'proposed',
		createdAt: '2026-01-01T00:00:00+00:00',
		decidedAt: null,
		result: null,
		...overrides,
	}
}

function mountCard(value = action()) {
	return mount(AiProposalCard, {
		props: { action: value },
		global: {
			stubs: {
				NcButton: NcButtonStub,
				NcNoteCard: NcNoteCardStub,
				NcLoadingIcon: NcLoadingIconStub,
			},
		},
	})
}

function button(wrapper: ReturnType<typeof mountCard>, text: string) {
	return wrapper.findAll('button').find((candidate) => candidate.text().trim() === text)
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('AiProposalCard', () => {
	it('previews a proposed asset and confirms it', async () => {
		vi.mocked(api.ai.actions.confirm).mockResolvedValue(action({ status: 'applied' }))
		const wrapper = mountCard()

		expect(wrapper.text()).toContain('Create a summary asset')
		expect(wrapper.text()).toContain('# Summary')

		await button(wrapper, 'Approve')?.trigger('click')
		await flushPromises()

		expect(api.ai.actions.confirm).toHaveBeenCalledWith(9)
		expect(wrapper.emitted('decided')).toHaveLength(1)
	})

	it('rejects a proposal', async () => {
		vi.mocked(api.ai.actions.reject).mockResolvedValue(action({ status: 'rejected' }))
		const wrapper = mountCard()

		await button(wrapper, 'Reject')?.trigger('click')
		await flushPromises()

		expect(api.ai.actions.reject).toHaveBeenCalledWith(9)
		expect(wrapper.emitted('decided')).toHaveLength(1)
	})

	it('does not show action buttons after a decision', () => {
		const wrapper = mountCard(action({ status: 'applied' }))

		expect(button(wrapper, 'Approve')).toBeUndefined()
		expect(button(wrapper, 'Reject')).toBeUndefined()
	})
})
