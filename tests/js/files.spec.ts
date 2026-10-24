import { describe, expect, it, vi } from 'vitest'

const { generateUrl } = vi.hoisted(() => ({
	generateUrl: vi.fn((route: string, params: Record<string, string | number>) => `/index.php/f/${params.fileId}`),
}))

vi.mock('@nextcloud/router', () => ({ generateUrl }))

import { assetFileUrl } from '../../src/utils/files'

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
