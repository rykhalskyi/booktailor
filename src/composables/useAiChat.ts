import type { AiAction, AiChatRequest } from '../types'
import { ref } from 'vue'
import { api, ApiError } from '../api/client'

export interface AiChatMessage {
	id: number
	role: 'user' | 'assistant'
	content: string
	proposals: AiAction[]
	warnings: string[]
	traceId: string | null
}

export function createAiChat() {
	const messages = ref<AiChatMessage[]>([])
	const loading = ref(false)
	const error = ref<string | null>(null)
	const lastRequest = ref<AiChatRequest | null>(null)
	let counter = 0
	let controller: AbortController | null = null

	function messageFor(errorValue: unknown, fallback: string): string {
		return errorValue instanceof ApiError || errorValue instanceof Error
			? errorValue.message
			: fallback
	}

	async function send(request: AiChatRequest, appendUser = true): Promise<void> {
		if (loading.value) {
			return
		}
		error.value = null
		lastRequest.value = request
		if (appendUser) {
			messages.value.push({
				id: ++counter,
				role: 'user',
				content: request.message,
				proposals: [],
				warnings: [],
				traceId: null,
			})
		}

		loading.value = true
		controller = new AbortController()
		try {
			const response = await api.ai.chat(request, controller.signal)
			messages.value.push({
				id: ++counter,
				role: 'assistant',
				content: response.message,
				proposals: response.proposedActions,
				warnings: response.warnings,
				traceId: response.traceId,
			})
		} catch (errorValue) {
			if (errorValue instanceof DOMException && errorValue.name === 'AbortError') {
				error.value = 'The AI request was cancelled.'
			} else {
				error.value = messageFor(errorValue, 'The AI request failed. Please try again.')
			}
		} finally {
			loading.value = false
			controller = null
		}
	}

	function abort(): void {
		controller?.abort()
	}

	async function retry(): Promise<void> {
		if (lastRequest.value !== null) {
			await send(lastRequest.value, false)
		}
	}

	function updateAction(action: AiAction): void {
		for (const message of messages.value) {
			const index = message.proposals.findIndex((candidate) => candidate.id === action.id)
			if (index !== -1) {
				message.proposals[index] = action
			}
		}
	}

	function reset(): void {
		messages.value = []
		error.value = null
		lastRequest.value = null
		loading.value = false
		controller?.abort()
		controller = null
	}

	return {
		abort,
		error,
		lastRequest,
		loading,
		messages,
		reset,
		retry,
		send,
		updateAction,
	}
}

export type AiChat = ReturnType<typeof createAiChat>
