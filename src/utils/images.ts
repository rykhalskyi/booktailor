import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import type { ChapterImage } from '../types'

export function imagePreviewUrl(fileId: number): string {
	const url = generateUrl('/core/preview')
	const params = new URLSearchParams({
		fileId: String(fileId),
		x: '320',
		y: '240',
		forceIcon: '0',
	})

	return `${url}?${params.toString()}`
}

export function imageMarkdownSnippet(image: ChapterImage): string {
	const alt = image.name.replace(/\.[^.]+$/, '').replaceAll('[', '').replaceAll(']', '')
	const name = image.path.replace('images/', '')

	return `![${alt}](images/${name})`
}

export function formatFileSize(bytes: number): string {
	if (bytes < 1024) {
		return `${bytes} B`
	}
	if (bytes < 1024 * 1024) {
		return `${(bytes / 1024).toFixed(1)} KB`
	}

	return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

export function imageReferenceLabel(references: number): string {
	if (references === 0) {
		return t('booktailor', 'Not referenced')
	}

	return references === 1
		? t('booktailor', 'Used in 1 asset')
		: t('booktailor', 'Used in {count} assets', { count: references })
}
