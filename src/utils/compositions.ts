import { generateUrl } from '@nextcloud/router'
import type { Composition, CompositionStatus } from '../types'

const STATUS_LABELS: Record<CompositionStatus, string> = {
	queued: 'Queued',
	running: 'Running',
	succeeded: 'Succeeded',
	failed: 'Failed',
}

function pad(value: number): string {
	return String(value).padStart(2, '0')
}

export function defaultCompositionName(now: Date = new Date()): string {
	const date = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
	const time = `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`

	return `Composition-${date}-${time}`
}

export function compositionTitle(composition: Composition): string {
	const name = composition.outputName?.trim()

	return name !== undefined && name !== '' ? name : composition.structureName
}

export function compositionStatusLabel(composition: Composition): string {
	if (composition.status === 'running' && composition.stale) {
		return 'Stale'
	}

	return STATUS_LABELS[composition.status]
}

export function compositionStatusClass(composition: Composition): string {
	return composition.status === 'running' && composition.stale
		? 'status-stale'
		: `status-${composition.status}`
}

export function compositionFileUrl(composition: Composition): string | null {
	return composition.outputFileId === null
		? null
		: generateUrl('/f/{fileId}', { fileId: composition.outputFileId })
}

export function compositionFileName(composition: Composition): string {
	const name = composition.outputPath?.split('/').pop()

	return name !== undefined && name !== '' ? name : `composition-${composition.id}.md`
}

export function saveBlob(blob: Blob, fileName: string): void {
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = fileName
	link.click()
	URL.revokeObjectURL(url)
}
