import { describe, expect, it } from 'vitest'
import { formatUpdatedAt } from '../../src/utils/dates'

const now = new Date('2026-10-30T12:00:00Z')

describe('formatUpdatedAt', () => {
	it('describes very recent edits as now', () => {
		expect(formatUpdatedAt('2026-10-30T12:00:00Z', now, 'en-US')).toBe('now')
		expect(formatUpdatedAt('2026-10-30T11:59:45Z', now, 'en-US')).toBe('15 sec. ago')
	})

	it('uses relative units for edits within a week', () => {
		expect(formatUpdatedAt('2026-10-30T10:00:00Z', now, 'en-US')).toBe('2 hr. ago')
		expect(formatUpdatedAt('2026-10-25T12:00:00Z', now, 'en-US')).toBe('5 days ago')
	})

	it('falls back to a date for older edits', () => {
		expect(formatUpdatedAt('2026-01-01T00:00:00Z', now, 'en-US')).toBe('Jan 1, 2026')
	})

	it('returns an empty string for invalid dates', () => {
		expect(formatUpdatedAt('not a date', now, 'en-US')).toBe('')
	})
})
