import { expect, test } from '@playwright/test'
import { openApp } from './helpers'

test('shows storage, defaults, and version information in Settings', async ({ page }) => {
	await openApp(page)
	await page.getByRole('link', { name: 'Settings' }).click()

	await expect(page.getByRole('heading', { name: 'Settings', exact: true })).toBeVisible()
	await expect(page.getByText('Storage location')).toBeVisible()
	await expect(page.getByText('BookTailor/ (in your files)')).toBeVisible()
	await expect(page.getByText('Nextcloud Text')).toBeVisible()
	await expect(page.getByText('Markdown in compositions/')).toBeVisible()
	await expect(page.getByText('AI provider profiles')).toBeVisible()
})
