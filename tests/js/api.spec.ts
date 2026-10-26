import { afterEach, describe, expect, it, vi } from 'vitest'
import { api, ApiError } from '../../src/api/client'

function response(body: unknown, status = 200) {
	return {
		ok: status >= 200 && status < 300,
		status,
		statusText: status >= 400 ? 'Error' : 'OK',
		text: async () => JSON.stringify(body),
	}
}

describe('API client', () => {
	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('unwraps the OCS data envelope', async () => {
		const fetchMock = vi.fn().mockResolvedValue(
			response({ ocs: { meta: { statuscode: 200 }, data: [{ id: 1, name: 'Book' }] } }),
		)
		vi.stubGlobal('fetch', fetchMock)

		const books = await api.books.list()

		expect(books).toEqual([{ id: 1, name: 'Book' }])
		expect(fetchMock).toHaveBeenCalledOnce()
	})

	it('throws an ApiError with the OCS message', async () => {
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
			response({
				ocs: {
					meta: { statuscode: 404, message: 'Not found' },
					data: { message: 'Book not found' },
				},
			}, 404),
		))

		await expect(api.books.list()).rejects.toBeInstanceOf(ApiError)
		await expect(api.books.list()).rejects.toThrow('Book not found')
	})

	it('sends structure create and delete requests', async () => {
		const fetchMock = vi.fn()
			.mockResolvedValueOnce(response({
				ocs: {
					meta: { statuscode: 201 },
					data: {
						id: 5,
						bookId: 7,
						name: 'Study',
						isDefault: false,
						createdAt: '2026-01-01T00:00:00+00:00',
						updatedAt: '2026-01-01T00:00:00+00:00',
					},
				},
			}, 201))
			.mockResolvedValueOnce(response({
				ocs: {
					meta: { statuscode: 200 },
					data: {
						id: 5,
						bookId: 7,
						name: 'Final',
						isDefault: false,
						createdAt: '2026-01-01T00:00:00+00:00',
						updatedAt: '2026-01-01T00:00:00+00:00',
					},
				},
			}))
			.mockResolvedValueOnce(response({
				ocs: { meta: { statuscode: 204 }, data: [] },
			}, 204))
		vi.stubGlobal('fetch', fetchMock)

		const created = await api.structures.create(7, 'Study')
		expect(created.id).toBe(5)

		const renamed = await api.structures.update(5, 'Final')
		expect(renamed.name).toBe('Final')

		await api.structures.remove(5)

		const [createUrl, createOptions] = fetchMock.mock.calls[0]
		expect(createUrl).toContain('/books/7/structures')
		expect(createOptions.method).toBe('POST')
		expect(createOptions.body).toBe(JSON.stringify({ name: 'Study' }))

		const [updateUrl, updateOptions] = fetchMock.mock.calls[1]
		expect(updateUrl).toContain('/structures/5')
		expect(updateOptions.method).toBe('PATCH')
		expect(updateOptions.body).toBe(JSON.stringify({ name: 'Final' }))

		const [deleteUrl, deleteOptions] = fetchMock.mock.calls[2]
		expect(deleteUrl).toContain('/structures/5')
		expect(deleteOptions.method).toBe('DELETE')
	})

	it('replaces structure entries', async () => {
		const entries = [
			{ chapterId: 1, included: true, position: 1, effectiveParentId: null },
			{ chapterId: 2, included: false, position: 1, effectiveParentId: 1 },
		]
		const fetchMock = vi.fn().mockResolvedValue(response({
			ocs: { meta: { statuscode: 200 }, data: entries },
		}))
		vi.stubGlobal('fetch', fetchMock)

		const replaced = await api.structures.replaceEntries(9, entries)

		expect(replaced).toEqual(entries)
		const [url, options] = fetchMock.mock.calls[0]
		expect(url).toContain('/structures/9/entries')
		expect(options.method).toBe('PUT')
		expect(options.body).toBe(JSON.stringify({ entries }))
	})

	it('moves a chapter with an explicit root parent', async () => {
		const fetchMock = vi.fn().mockResolvedValue(response({
			ocs: {
				meta: { statuscode: 200 },
				data: {
					id: 3,
					bookId: 1,
					parentId: null,
					name: 'Chapter',
					fileId: 9,
					children: [],
					createdAt: '2026-01-01T00:00:00+00:00',
					updatedAt: '2026-01-01T00:00:00+00:00',
				},
			},
		}))
		vi.stubGlobal('fetch', fetchMock)

		await api.chapters.move(3, null)

		const [url, options] = fetchMock.mock.calls[0]
		expect(url).toContain('/chapters/3')
		expect(options.method).toBe('PATCH')
		expect(options.body).toBe(JSON.stringify({ parentId: null }))
	})

	it('creates tags with a color and updates the color', async () => {
		const tag = { id: 3, bookId: 1, name: 'Raw', slug: 'raw', color: '#15803d' }
		const fetchMock = vi.fn()
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 201 }, data: tag } }, 201))
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 200 }, data: { ...tag, color: '#b91c1c' } } }))
		vi.stubGlobal('fetch', fetchMock)

		await api.tags.create(1, 'Raw', '#15803d')
		await api.tags.update(3, undefined, '#b91c1c')

		const [createUrl, createOptions] = fetchMock.mock.calls[0]
		expect(createUrl).toContain('/books/1/tags')
		expect(createOptions.method).toBe('POST')
		expect(createOptions.body).toBe(JSON.stringify({ name: 'Raw', color: '#15803d' }))

		const [updateUrl, updateOptions] = fetchMock.mock.calls[1]
		expect(updateUrl).toContain('/tags/3')
		expect(updateOptions.method).toBe('PATCH')
		expect(updateOptions.body).toBe(JSON.stringify({ color: '#b91c1c' }))
	})
})

describe('composition API', () => {
	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('lists, creates, retries, and deletes compositions', async () => {
		const composition = {
			id: 9,
			bookId: 1,
			structureId: 3,
			structureName: 'Default',
			status: 'queued',
			rules: [{ tagId: 4, priority: 1, fallback: false }],
			outputFileId: null,
			outputPath: null,
			error: null,
			stale: false,
			createdAt: '2026-01-01T00:00:00+00:00',
			startedAt: null,
			completedAt: null,
		}
		const fetchMock = vi.fn()
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 200 }, data: [composition] } }))
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 202 }, data: composition } }, 202))
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 202 }, data: composition } }, 202))
			.mockResolvedValueOnce(response({ ocs: { meta: { statuscode: 204 }, data: [] } }, 204))
		vi.stubGlobal('fetch', fetchMock)

		expect(await api.compositions.list(1)).toEqual([composition])
		expect(await api.compositions.create(1, 3, composition.rules, 'Final')).toEqual(composition)
		expect(await api.compositions.retry(9)).toEqual(composition)
		await api.compositions.remove(9)

		const [listUrl] = fetchMock.mock.calls[0]
		expect(listUrl).toContain('/books/1/compositions')

		const [createUrl, createOptions] = fetchMock.mock.calls[1]
		expect(createUrl).toContain('/books/1/compositions')
		expect(createOptions.method).toBe('POST')
		expect(createOptions.body).toBe(JSON.stringify({
			structureId: 3,
			rules: composition.rules,
			name: 'Final',
		}))

		const [retryUrl, retryOptions] = fetchMock.mock.calls[2]
		expect(retryUrl).toContain('/compositions/9/retry')
		expect(retryOptions.method).toBe('POST')

		const [deleteUrl, deleteOptions] = fetchMock.mock.calls[3]
		expect(deleteUrl).toContain('/compositions/9')
		expect(deleteOptions.method).toBe('DELETE')
	})

	it('downloads a composition as a blob', async () => {
		const fetchMock = vi.fn().mockResolvedValue({
			ok: true,
			status: 200,
			statusText: 'OK',
			blob: async () => new Blob(['# Book'], { type: 'text/markdown' }),
		})
		vi.stubGlobal('fetch', fetchMock)

		const blob = await api.compositions.download(9)

		expect(blob).toBeInstanceOf(Blob)
		expect(await blob.text()).toBe('# Book')
		const [url, options] = fetchMock.mock.calls[0]
		expect(url).toContain('/compositions/9/download')
		expect(options.headers.Accept).toBe('text/markdown')
	})

	it('reports a composition download error', async () => {
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
			ok: false,
			status: 409,
			statusText: 'Conflict',
			text: async () => JSON.stringify({
				ocs: { meta: { statuscode: 409 }, data: { message: 'The composition output is not ready' } },
			}),
			blob: async () => new Blob([]),
		}))

		await expect(api.compositions.download(9)).rejects.toThrow('The composition output is not ready')
	})
})
