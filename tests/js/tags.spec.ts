import { describe, expect, it } from 'vitest'
import {
	DEFAULT_TAG_COLOR,
	TAG_COLORS,
	normalizeTagColor,
	tagChipStyle,
	tagTextColor,
} from '../../src/utils/tags'

describe('tag colors', () => {
	it('offers a ten-color palette with unique values', () => {
		expect(TAG_COLORS).toHaveLength(10)
		expect(new Set(TAG_COLORS.map((color) => color.value)).size).toBe(10)
		expect(TAG_COLORS[0].value).toBe(DEFAULT_TAG_COLOR)
	})

	it('normalizes valid hex colors and falls back to the default', () => {
		expect(normalizeTagColor('#15803D')).toBe('#15803d')
		expect(normalizeTagColor(undefined)).toBe(DEFAULT_TAG_COLOR)
		expect(normalizeTagColor('not-a-color')).toBe(DEFAULT_TAG_COLOR)
	})

	it('uses readable text on light and dark backgrounds', () => {
		expect(tagTextColor('#ffffff')).toBe('#1a1a1a')
		expect(tagTextColor('#000000')).toBe('#ffffff')
		expect(tagTextColor('#2563eb')).toBe('#ffffff')
	})

	it('builds a chip style from the tag color', () => {
		expect(tagChipStyle('#b91c1c')).toEqual({
			backgroundColor: '#b91c1c',
			color: '#ffffff',
		})
	})
})
