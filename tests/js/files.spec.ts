import { describe, expect, it, vi } from 'vitest'

const { generateUrl } = vi.hoisted(() => ({
	generateUrl: vi.fn((route: string, params?: Record<string, string | number>) => (
		route === '/f/{fileId}'
			? `/index.php/f/${params?.fileId}`
			: `/index.php${route}`
	)),
}))

vi.mock('@nextcloud/router', () => ({ generateUrl }))

import type { Asset } from '../../src/types'
import { assetEditorUrl, assetFileUrl, assetFolderPath } from '../../src/utils/files'

function asset(path: string | null, fileId: number | null = 42): Asset {
	return {
		id: 1,
		bookId: 1,
		chapterId: 1,
		name: 'Notes',
		fileName: 'Notes.md',
		fileId,
		path,
		tags: [],
		createdAt: '2026-01-01T00:00:00+00:00',
		updatedAt: '2026-01-01T00:00:00+00:00',
	}
}

describe('assetFileUrl', () => {
	it('returns null when the asset has no file', () => {
		expect(assetFileUrl(null)).toBeNull()
		expect(generateUrl).not.toHaveBeenCalled()
	})

	it('builds the file route through the Nextcloud router', () => {
		expect(assetFileUrl(42)).toBe('/index.php/f/42')
		expect(generateUrl).toHaveBeenCalledWith('/f/{fileId}', { fileId: 42 })
	})
})

describe('assetEditorUrl', () => {
	it('opens the file in the Files app with its folder and file id', () => {
		const url = assetEditorUrl(asset('BookTailor/Book/Chapter/Notes.md'))

		expect(url).toBe('/index.php/apps/files/?dir=BookTailor%2FBook%2FChapter&openfile=42')
	})

	it('falls back to the file route when the path is unknown', () => {
		expect(assetEditorUrl(asset(null))).toBe('/index.php/f/42')
	})

	it('does not build a URL for a missing file', () => {
		expect(assetEditorUrl(asset('BookTailor/Book/Chapter/Notes.md', null))).toBeNull()
	})

	it('derives the folder from the asset path', () => {
		expect(assetFolderPath(asset('BookTailor/Book/Chapter/Notes.md'))).toBe('BookTailor/Book/Chapter')
		expect(assetFolderPath(asset(null))).toBeNull()
	})
})
