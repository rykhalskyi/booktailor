import { generateOcsUrl } from '@nextcloud/router'
import type { Asset, Book, Chapter, Structure } from '../types'

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

export const api = {
	books: {
		list: (): Promise<Book[]> => request<Book[]>('/books'),
		create: (name: string, description?: string): Promise<Book> => request<Book>('/books', {
			method: 'POST',
			body: { name, description: description ?? null },
		}),
	},
	structures: {
		list: (bookId: number): Promise<Structure[]> => request<Structure[]>(`/books/${bookId}/structures`),
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
	},
}
