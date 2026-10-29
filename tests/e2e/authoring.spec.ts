import { expect, test } from '@playwright/test'
import { createApi, openApp, selectBook } from './helpers'

test('creates and navigates a book, chapter, and asset', async ({ page }) => {
	const api = await createApi()
	const suffix = Date.now().toString(36)
	const book = await api.book(`E2E Authoring ${suffix}`)
	const chapter = await api.chapter(book.id, 'Chapter One')
	await api.asset(chapter.id, 'Draft', '# Draft\n\nE2E content')

	try {
		await openApp(page)
		await selectBook(page, book.name)

		await expect(page.locator('.chapter-name', { hasText: 'Chapter One' })).toBeVisible()
		await page.locator('.chapter-name', { hasText: 'Chapter One' }).click()
		await page.getByRole('button', { name: 'Actions for Chapter One' }).click()
		await page.getByText('Edit assets', { exact: true }).click()

		await expect(page.getByRole('heading', { name: 'Chapter One' })).toBeVisible()
		await expect(page.getByText('Draft.md', { exact: true })).toBeVisible()
	} finally {
		await api.deleteBook(book.id)
		await api.dispose()
	}
})
