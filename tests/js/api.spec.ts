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
})
