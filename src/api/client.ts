import { generateOcsUrl } from '@nextcloud/router'
import type { AiAction, AiChatRequest, AiChatResponse, AiProfile, AiProfilePayload, AiPrompt, AiSettings, Asset, Book, Capabilities, Chapter, Composition, CompositionRule, Structure, StructureEntry, Tag, Version } from '../types'

declare global {
	interface Window {
		OC?: {
			requestToken?: string
		}
	}
}

const API_ROOT = generateOcsUrl('/apps/booktailor/api/v1')

interface OcsMeta {
	status?: string
	statuscode: number
	message?: string
}

interface OcsResponse<T> {
	ocs: {
		meta: OcsMeta
		data: T
	}
}

export class ApiError extends Error {
	public readonly status: number

	public constructor(message: string, status: number) {
		super(message)
		this.name = 'ApiError'
		this.status = status
	}
}

interface RequestOptions {
	method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
	body?: unknown
	formData?: FormData
	query?: Record<string, string | number | boolean | null | undefined>
	signal?: AbortSignal
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
	const params = new URLSearchParams()
	if (query) {
		for (const [key, value] of Object.entries(query)) {
			if (value !== undefined && value !== null) {
				params.set(key, String(value))
			}
		}
	}

	const suffix = params.size > 0 ? `?${params.toString()}` : ''

	return `${API_ROOT}${path}${suffix}`
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
	const headers: Record<string, string> = {
		Accept: 'application/json',
		'OCS-APIRequest': 'true',
	}
	const requestToken = window.OC?.requestToken
	if (requestToken) {
		headers.requesttoken = requestToken
	}

	let body: BodyInit | undefined
	if (options.formData) {
		body = options.formData
	} else if (options.body !== undefined) {
		headers['Content-Type'] = 'application/json'
		body = JSON.stringify(options.body)
	}

	const response = await fetch(buildUrl(path, options.query), {
		method: options.method ?? 'GET',
		credentials: 'same-origin',
		headers,
		body,
		signal: options.signal,
	})

	const raw = await response.text()
	let payload: OcsResponse<T> | null = null
	if (raw !== '') {
		try {
			payload = JSON.parse(raw) as OcsResponse<T>
		} catch {
			throw new ApiError(response.statusText || 'Invalid server response', response.status)
		}
	}

	const status = payload?.ocs?.meta?.statuscode ?? response.status
	if (!response.ok || status >= 400) {
		const message = (payload?.ocs?.data as { message?: string } | undefined)?.message
			?? payload?.ocs?.meta?.message
			?? response.statusText
			?? 'Request failed'
		throw new ApiError(message, status)
	}

	return (payload?.ocs?.data ?? ([] as unknown)) as T
}

async function requestBlob(path: string): Promise<Blob> {
	const headers: Record<string, string> = {
		Accept: 'text/markdown',
		'OCS-APIRequest': 'true',
	}
	const requestToken = window.OC?.requestToken
	if (requestToken) {
		headers.requesttoken = requestToken
	}

	const response = await fetch(buildUrl(path), {
		method: 'GET',
		credentials: 'same-origin',
		headers,
	})

	if (!response.ok) {
		let message = response.statusText || 'Request failed'
		try {
			const raw = await response.text()
			const payload = JSON.parse(raw) as OcsResponse<{ message?: string }>
			message = payload?.ocs?.data?.message ?? payload?.ocs?.meta?.message ?? message
		} catch {
			// Keep the HTTP status text.
		}
		throw new ApiError(message, response.status)
	}

	return response.blob()
}

export const api = {
	capabilities: (): Promise<Capabilities> => request<Capabilities>('/capabilities'),
	books: {
		list: (): Promise<Book[]> => request<Book[]>('/books'),
		create: (name: string, description?: string): Promise<Book> => request<Book>('/books', {
			method: 'POST',
			body: { name, description: description ?? null },
		}),
	},
	structures: {
		list: (bookId: number): Promise<Structure[]> => request<Structure[]>(`/books/${bookId}/structures`),
		create: (bookId: number, name: string): Promise<Structure> => request<Structure>(`/books/${bookId}/structures`, {
			method: 'POST',
			body: { name },
		}),
		update: (structureId: number, name: string): Promise<Structure> => request<Structure>(`/structures/${structureId}`, {
			method: 'PATCH',
			body: { name },
		}),
		remove: (structureId: number): Promise<void> => request<void>(`/structures/${structureId}`, {
			method: 'DELETE',
		}),
		entries: (structureId: number): Promise<StructureEntry[]> => request<StructureEntry[]>(`/structures/${structureId}/entries`),
		replaceEntries: (structureId: number, entries: StructureEntry[]): Promise<StructureEntry[]> => request<StructureEntry[]>(`/structures/${structureId}/entries`, {
			method: 'PUT',
			body: { entries },
		}),
	},
	chapters: {
		tree: (bookId: number, structureId?: number): Promise<Chapter[]> => request<Chapter[]>(`/books/${bookId}/chapters`, {
			query: { structureId },
		}),
		create: (bookId: number, name: string, parentId: number | null): Promise<Chapter> => request<Chapter>(`/books/${bookId}/chapters`, {
			method: 'POST',
			body: { name, parentId },
		}),
		rename: (chapterId: number, name: string): Promise<Chapter> => request<Chapter>(`/chapters/${chapterId}`, {
			method: 'PATCH',
			body: { name },
		}),
		move: (chapterId: number, parentId: number | null): Promise<Chapter> => request<Chapter>(`/chapters/${chapterId}`, {
			method: 'PATCH',
			body: { parentId },
		}),
		remove: (chapterId: number): Promise<void> => request<void>(`/chapters/${chapterId}`, {
			method: 'DELETE',
		}),
	},
	assets: {
		list: (chapterId: number): Promise<Asset[]> => request<Asset[]>(`/chapters/${chapterId}/assets`),
		create: (chapterId: number, name: string): Promise<Asset> => request<Asset>(`/chapters/${chapterId}/assets`, {
			method: 'POST',
			body: { name },
		}),
		upload: (chapterId: number, file: File): Promise<Asset> => {
			const formData = new FormData()
			formData.append('file', file)

			return request<Asset>(`/chapters/${chapterId}/assets/upload`, {
				method: 'POST',
				formData,
			})
		},
		remove: (assetId: number): Promise<void> => request<void>(`/assets/${assetId}`, {
			method: 'DELETE',
		}),
		content: (assetId: number): Promise<{ content: string }> => request<{ content: string }>(`/assets/${assetId}/content`),
		updateContent: (assetId: number, content: string): Promise<{ content: string }> => request<{ content: string }>(`/assets/${assetId}/content`, {
			method: 'PUT',
			body: { content },
		}),
	},
	tags: {
		list: (bookId: number): Promise<Tag[]> => request<Tag[]>(`/books/${bookId}/tags`),
		create: (bookId: number, name: string, color?: string): Promise<Tag> => request<Tag>(`/books/${bookId}/tags`, {
			method: 'POST',
			body: { name, color },
		}),
		update: (tagId: number, name?: string, color?: string): Promise<Tag> => request<Tag>(`/tags/${tagId}`, {
			method: 'PATCH',
			body: { name, color },
		}),
		remove: (tagId: number): Promise<void> => request<void>(`/tags/${tagId}`, {
			method: 'DELETE',
		}),
		forAsset: (assetId: number): Promise<Tag[]> => request<Tag[]>(`/assets/${assetId}/tags`),
		replaceForAsset: (assetId: number, tagIds: number[]): Promise<Tag[]> => request<Tag[]>(`/assets/${assetId}/tags`, {
			method: 'PUT',
			body: { tagIds },
		}),
	},
	compositions: {
		list: (bookId: number, status?: string): Promise<Composition[]> => request<Composition[]>(`/books/${bookId}/compositions`, {
			query: { status },
		}),
		create: (bookId: number, structureId: number, rules: CompositionRule[], name?: string): Promise<Composition> => request<Composition>(`/books/${bookId}/compositions`, {
			method: 'POST',
			body: { structureId, rules, name: name ?? null },
		}),
		get: (compositionId: number): Promise<Composition> => request<Composition>(`/compositions/${compositionId}`),
		remove: (compositionId: number): Promise<void> => request<void>(`/compositions/${compositionId}`, {
			method: 'DELETE',
		}),
		retry: (compositionId: number): Promise<Composition> => request<Composition>(`/compositions/${compositionId}/retry`, {
			method: 'POST',
		}),
		download: (compositionId: number): Promise<Blob> => requestBlob(`/compositions/${compositionId}/download`),
	},
	ai: {
		settings: (): Promise<AiSettings> => request<AiSettings>('/ai/settings'),
		updateSettings: (contentLogging: boolean): Promise<{ contentLogging: boolean }> => request<{ contentLogging: boolean }>('/ai/settings', {
			method: 'PATCH',
			body: { contentLogging },
		}),
		profiles: {
			list: (): Promise<AiSettings> => request<AiSettings>('/ai/settings'),
			create: (payload: AiProfilePayload): Promise<AiProfile> => request<AiProfile>('/ai/profiles', {
				method: 'POST',
				body: payload,
			}),
			update: (profileId: number, payload: AiProfilePayload): Promise<AiProfile> => request<AiProfile>(`/ai/profiles/${profileId}`, {
				method: 'PATCH',
				body: payload,
			}),
			remove: (profileId: number): Promise<void> => request<void>(`/ai/profiles/${profileId}`, {
				method: 'DELETE',
			}),
			activate: (profileId: number, active = true): Promise<AiProfile[]> => request<AiProfile[]>(`/ai/profiles/${profileId}/activate`, {
				method: 'POST',
				body: { active },
			}),
			test: (profileId: number): Promise<{ ok: boolean, model: string, message: string }> => request<{ ok: boolean, model: string, message: string }>(`/ai/profiles/${profileId}/test`, {
				method: 'POST',
			}),
		},
		chat: (payload: AiChatRequest, signal?: AbortSignal): Promise<AiChatResponse> => request<AiChatResponse>('/ai/chat', {
			method: 'POST',
			body: payload,
			signal,
		}),
		actions: {
			list: (bookId: number, status?: string): Promise<AiAction[]> => request<AiAction[]>(`/books/${bookId}/ai-actions`, {
				query: { status },
			}),
			get: (actionId: number): Promise<AiAction> => request<AiAction>(`/ai-actions/${actionId}`),
			confirm: (actionId: number): Promise<AiAction> => request<AiAction>(`/ai-actions/${actionId}/confirm`, {
				method: 'POST',
			}),
			reject: (actionId: number): Promise<AiAction> => request<AiAction>(`/ai-actions/${actionId}/reject`, {
				method: 'POST',
			}),
		},
		prompts: {
			list: (bookId: number): Promise<AiPrompt[]> => request<AiPrompt[]>(`/books/${bookId}/prompts`),
			create: (bookId: number, name: string, text: string): Promise<AiPrompt> => request<AiPrompt>(`/books/${bookId}/prompts`, {
				method: 'POST',
				body: { name, text },
			}),
			update: (promptId: number, name?: string, text?: string): Promise<AiPrompt> => request<AiPrompt>(`/prompts/${promptId}`, {
				method: 'PATCH',
				body: { name, text },
			}),
			remove: (promptId: number): Promise<void> => request<void>(`/prompts/${promptId}`, {
				method: 'DELETE',
			}),
		},
	},
	versions: {
		list: (assetId: number): Promise<Version[]> => request<Version[]>(`/assets/${assetId}/versions`),
		checkIn: (assetId: number, message: string | null): Promise<Version> => request<Version>(`/assets/${assetId}/versions`, {
			method: 'POST',
			body: { message },
		}),
		restore: (assetId: number, revisionId: string): Promise<Version> => request<Version>(`/assets/${assetId}/versions/${encodeURIComponent(revisionId)}/restore`, {
			method: 'POST',
		}),
		content: (assetId: number, revisionId: string): Promise<{ revisionId: string, content: string }> => request<{ revisionId: string, content: string }>(`/assets/${assetId}/versions/${encodeURIComponent(revisionId)}/content`),
	},
}
