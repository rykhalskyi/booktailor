import { generateUrl } from '@nextcloud/router'
import type { Asset } from '../types'

export function assetFileUrl(fileId: number | null): string | null {
	return fileId === null ? null : generateUrl('/f/{fileId}', { fileId })
}

export function assetFolderPath(asset: Asset): string | null {
	if (asset.path === null) {
		return null
	}

	const segments = asset.path.split('/')
	segments.pop()

	return segments.join('/')
}

export function assetEditorUrl(asset: Asset): string | null {
	if (asset.fileId === null || asset.path === null) {
		return assetFileUrl(asset.fileId)
	}

	const params = new URLSearchParams({
		dir: assetFolderPath(asset) ?? '',
		openfile: String(asset.fileId),
	})

	return `${generateUrl('/apps/files/')}?${params.toString()}`
}
