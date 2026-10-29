import { expect, test } from '@playwright/test'
import { createApi, openApp, pngBuffer, selectBook } from './helpers'

test('uploads, replaces, and deletes a chapter image', async ({ page }) => {
	const api = await createApi()
	const suffix = Date.now().toString(36)
	const book = await api.book(`E2E Images ${suffix}`)
	const chapter = await api.chapter(book.id, 'Illustrated')
	await api.asset(chapter.id, 'Text', 'Text without images')

	try {
		await openApp(page)
		await selectBook(page, book.name)
		await page.locator('.chapter-name', { hasText: 'Illustrated' }).click()
		await page.getByRole('button', { name: 'Actions for Illustrated' }).click()
		await page.getByText('Edit assets', { exact: true }).click()

		await expect(page.getByRole('heading', { name: 'Images' })).toBeVisible()

		const upload = page.waitForEvent('filechooser')
		await page.getByRole('button', { name: 'Upload image' }).first().click()
		await (await upload).setFiles({
			name: 'cover.png',
			mimeType: 'image/png',
			buffer: pngBuffer(),
		})

		await expect(page.getByText('cover.png', { exact: true })).toBeVisible()
		await expect(page.getByText(/Not referenced/)).toBeVisible()
		await expect.poll(
			() => page.locator('.image-preview').evaluate((image: HTMLImageElement) => image.naturalWidth),
		).toBeGreaterThan(0)

		const replace = page.waitForEvent('filechooser')
		await page.getByRole('button', { name: 'Replace' }).click()
		await (await replace).setFiles({
			name: 'cover.png',
			mimeType: 'image/png',
			buffer: pngBuffer(),
		})
		await expect(page.getByText('cover.png', { exact: true })).toBeVisible()

		await page.getByRole('button', { name: 'Delete', exact: true }).click()
		await page.getByRole('button', { name: 'Delete image' }).click()
		await expect(page.getByText('cover.png', { exact: true })).toHaveCount(0)
	} finally {
		await api.deleteBook(book.id)
		await api.dispose()
	}
})

test('blocks deleting a referenced image', async ({ page }) => {
	const api = await createApi()
	const suffix = Date.now().toString(36)
	const book = await api.book(`E2E Image References ${suffix}`)
	const chapter = await api.chapter(book.id, 'Referenced')
	const asset = await api.asset(chapter.id, 'Text', '![cover](images/cover.png)')
	await api.uploadImage(chapter.id, 'cover.png', pngBuffer())

	try {
		await openApp(page)
		await selectBook(page, book.name)
		await page.locator('.chapter-name', { hasText: 'Referenced' }).click()
		await page.getByRole('button', { name: 'Actions for Referenced' }).click()
		await page.getByText('Edit assets', { exact: true }).click()

		await expect(page.getByText(/Used in 1 asset/)).toBeVisible()
		await page.getByRole('button', { name: 'Delete', exact: true }).click()
		await page.getByRole('button', { name: 'Delete image' }).click()

		await expect(page.getByText(/is referenced by:/)).toBeVisible()
		await expect(page.getByText('cover.png', { exact: true })).toBeVisible()
	} finally {
		await api.updateAsset(asset.id, 'No image').catch(() => undefined)
		await api.deleteBook(book.id)
		await api.dispose()
	}
})
