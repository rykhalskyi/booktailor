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
				chat: vi.fn(),
			},
		},
	}
})

import { api } from '../../src/api/client'
import { createAiChat } from '../../src/composables/useAiChat'

beforeEach(() => {
	vi.clearAllMocks()
})

describe('useAiChat', () => {
	it('appends the user message and the assistant response', async () => {
		vi.mocked(api.ai.chat).mockResolvedValue({
			message: 'Here is the answer.',
			proposedActions: [],
			traceId: 'trace-1',
			warnings: [],
			usage: {},
		})
		const chat = createAiChat()

		await chat.send({ bookId: 1, message: 'Hello' })

		expect(api.ai.chat).toHaveBeenCalledWith({ bookId: 1, message: 'Hello' }, expect.any(AbortSignal))
		expect(chat.messages.value).toHaveLength(2)
		expect(chat.messages.value[0].role).toBe('user')
		expect(chat.messages.value[1].content).toBe('Here is the answer.')
		expect(chat.messages.value[1].traceId).toBe('trace-1')
		expect(chat.loading.value).toBe(false)
	})

	it('records an error and retries without duplicating the user message', async () => {
		vi.mocked(api.ai.chat)
			.mockRejectedValueOnce(new Error('Provider down'))
			.mockResolvedValueOnce({
				message: 'Recovered.',
				proposedActions: [],
				traceId: null,
				warnings: [],
				usage: {},
			})
		const chat = createAiChat()

		await chat.send({ bookId: 1, message: 'Hello' })
		expect(chat.error.value).toBe('Provider down')
		expect(chat.messages.value).toHaveLength(1)

		await chat.retry()
		expect(chat.messages.value).toHaveLength(2)
		expect(chat.messages.value[1].content).toBe('Recovered.')
		expect(api.ai.chat).toHaveBeenCalledTimes(2)
	})
})
