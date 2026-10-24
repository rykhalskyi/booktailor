import { generateUrl } from '@nextcloud/router'

export function assetFileUrl(fileId: number | null): string | null {
	return fileId === null ? null : generateUrl('/f/{fileId}', { fileId })
}
