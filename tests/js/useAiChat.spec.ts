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

import type { AiChatResponse } from '../../src/types'
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

		expect(api.ai.chat).toHaveBeenCalledWith({ bookId: 1, message: 'Hello', history: [] }, expect.any(AbortSignal))
		expect(chat.messages.value).toHaveLength(2)
		expect(chat.messages.value[0].role).toBe('user')
		expect(chat.messages.value[1].content).toBe('Here is the answer.')
		expect(chat.messages.value[1].traceId).toBe('trace-1')
		expect(chat.loading.value).toBe(false)
	})

	it('sends prior messages as history and excludes the current message', async () => {
		vi.mocked(api.ai.chat)
			.mockResolvedValueOnce({
				message: 'First answer.',
				proposedActions: [],
				traceId: null,
				warnings: [],
				usage: {},
			})
			.mockResolvedValueOnce({
				message: 'Second answer.',
				proposedActions: [],
				traceId: null,
				warnings: [],
				usage: {},
			})
		const chat = createAiChat()

		await chat.send({ bookId: 1, message: 'First question' })
		await chat.send({ bookId: 1, message: 'Follow up' })

		expect(api.ai.chat).toHaveBeenNthCalledWith(2, {
			bookId: 1,
			message: 'Follow up',
			history: [
				{ role: 'user', content: 'First question' },
				{ role: 'assistant', content: 'First answer.' },
			],
		}, expect.any(AbortSignal))
	})

	it('ignores an in-flight response after the chat is reset', async () => {
		let resolveChat!: (value: AiChatResponse) => void
		vi.mocked(api.ai.chat).mockImplementation(() => new Promise((resolve) => {
			resolveChat = resolve
		}))
		const chat = createAiChat()

		const pending = chat.send({ bookId: 1, message: 'Hello' })
		expect(chat.loading.value).toBe(true)

		chat.reset()
		expect(chat.messages.value).toHaveLength(0)
		expect(chat.loading.value).toBe(false)

		resolveChat({
			message: 'Stale answer.',
			proposedActions: [],
			traceId: null,
			warnings: [],
			usage: {},
		})
		await pending

		expect(chat.messages.value).toHaveLength(0)
		expect(chat.error.value).toBeNull()
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
