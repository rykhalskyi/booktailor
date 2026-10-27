export type DiffLineType = 'same' | 'added' | 'removed'

export interface DiffLine {
	type: DiffLineType
	text: string
}

const MAX_LINES = 1200

export function lineDiff(oldText: string, newText: string): DiffLine[] {
	const oldLines = oldText.split(/\r?\n/).slice(0, MAX_LINES)
	const newLines = newText.split(/\r?\n/).slice(0, MAX_LINES)
	const rows = oldLines.length + 1
	const cols = newLines.length + 1
	const table: number[][] = Array.from({ length: rows }, () => Array.from({ length: cols }, () => 0))

	for (let i = oldLines.length - 1; i >= 0; i--) {
		for (let j = newLines.length - 1; j >= 0; j--) {
			table[i][j] = oldLines[i] === newLines[j]
				? table[i + 1][j + 1] + 1
				: Math.max(table[i + 1][j], table[i][j + 1])
		}
	}

	const result: DiffLine[] = []
	let i = 0
	let j = 0
	while (i < oldLines.length && j < newLines.length) {
		if (oldLines[i] === newLines[j]) {
			result.push({ type: 'same', text: oldLines[i] })
			i++
			j++
		} else if (table[i + 1][j] >= table[i][j + 1]) {
			result.push({ type: 'removed', text: oldLines[i] })
			i++
		} else {
			result.push({ type: 'added', text: newLines[j] })
			j++
		}
	}
	while (i < oldLines.length) {
		result.push({ type: 'removed', text: oldLines[i++] })
	}
	while (j < newLines.length) {
		result.push({ type: 'added', text: newLines[j++] })
	}

	return result
}
