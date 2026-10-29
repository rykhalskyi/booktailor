import { expect, request as playwrightRequest, type APIRequestContext, type Page } from '@playwright/test'

export const adminUser = process.env.BOOKTAILOR_ADMIN_USER ?? 'admin'
export const adminPassword = process.env.BOOKTAILOR_ADMIN_PASSWORD ?? 'admin'
export const appPath = '/index.php/apps/booktailor/'
const ocsRoot = '/ocs/v2.php/apps/booktailor/api/v1'

interface OcsResponse<T> {
	ocs: {
		meta: {
			statuscode: number
			message?: string
		}
		data: T
	}
}

interface ApiBook {
	id: number
	name: string
}

interface ApiChapter {
	id: number
	name: string
}

interface ApiAsset {
	id: number
	fileName: string
}

export interface E2eApi {
	client: APIRequestContext
	book(name: string): Promise<ApiBook>
	chapter(bookId: number, name: string): Promise<ApiChapter>
	asset(chapterId: number, name: string, content: string): Promise<ApiAsset>
	uploadImage(chapterId: number, name: string, content: Buffer): Promise<void>
	updateAsset(assetId: number, content: string): Promise<void>
	deleteBook(bookId: number): Promise<void>
	dispose(): Promise<void>
}

export async function createApi(): Promise<E2eApi> {
	const client = await playwrightRequest.newContext({
		baseURL: process.env.BOOKTAILOR_BASE_URL ?? 'http://nextcloud.local',
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${adminUser}:${adminPassword}`).toString('base64')}`,
		},
	})

	async function data<T>(response: Awaited<ReturnType<APIRequestContext['post']>>): Promise<T> {
		if (!response.ok()) {
			throw new Error(`E2E setup failed: ${response.status()} ${await response.text()}`)
		}

		const payload = await response.json() as OcsResponse<T>

		return payload.ocs.data
	}

	return {
		client,
		book: async (name: string): Promise<ApiBook> => data<ApiBook>(
			await client.post(`${ocsRoot}/books`, { data: { name } }),
		),
		chapter: async (bookId: number, name: string): Promise<ApiChapter> => data<ApiChapter>(
			await client.post(`${ocsRoot}/books/${bookId}/chapters`, { data: { name } }),
		),
		asset: async (chapterId: number, name: string, content: string): Promise<ApiAsset> => {
			const asset = await data<ApiAsset>(
				await client.post(`${ocsRoot}/chapters/${chapterId}/assets`, { data: { name } }),
			)
			await client.put(`${ocsRoot}/assets/${asset.id}/content`, { data: { content } })

			return asset
		},
		uploadImage: async (chapterId: number, name: string, content: Buffer): Promise<void> => {
			const response = await client.post(`${ocsRoot}/chapters/${chapterId}/images`, {
				multipart: {
					file: {
						name,
						mimeType: 'image/png',
						buffer: content,
					},
				},
			})
			if (!response.ok()) {
				throw new Error(`Image upload failed: ${response.status()} ${await response.text()}`)
			}
		},
		updateAsset: async (assetId: number, content: string): Promise<void> => {
			await client.put(`${ocsRoot}/assets/${assetId}/content`, { data: { content } })
		},
		deleteBook: async (bookId: number): Promise<void> => {
			await client.delete(`${ocsRoot}/books/${bookId}`)
		},
		dispose: async (): Promise<void> => {
			await client.dispose()
		},
	}
}

export async function login(page: Page): Promise<void> {
	await page.goto(appPath)
	if (!page.url().includes('/login')) {
		return
	}

	await page.waitForSelector('#user', { timeout: 30_000 })
	await page.fill('#user', adminUser)
	await page.fill('#password', adminPassword)
	await page.locator('button[type="submit"]').click()
	await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30_000 })
}

export async function openApp(page: Page): Promise<void> {
	await login(page)
	if (!page.url().includes('/apps/booktailor')) {
		await page.goto(appPath)
	}
	await expect(page.locator('.library-app')).toBeVisible()
}

export async function selectBook(page: Page, name: string): Promise<void> {
	const navigation = page.getByRole('navigation', { name: 'BookTailor books' })
	await navigation.getByText(name, { exact: true }).click()
	await expect(page.locator('.chapter-tree')).toBeVisible()
}

export function pngBuffer(): Buffer {
	return Buffer.from(
		'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
		'base64',
	)
}
