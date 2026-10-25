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
})
