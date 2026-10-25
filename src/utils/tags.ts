export interface TagColorOption {
	name: string
	value: string
}

export const TAG_COLORS: TagColorOption[] = [
	{ name: 'Blue', value: '#2563eb' },
	{ name: 'Green', value: '#15803d' },
	{ name: 'Teal', value: '#0f766e' },
	{ name: 'Orange', value: '#c2410c' },
	{ name: 'Red', value: '#b91c1c' },
	{ name: 'Purple', value: '#7e22ce' },
	{ name: 'Pink', value: '#be185d' },
	{ name: 'Amber', value: '#b45309' },
	{ name: 'Cyan', value: '#0e7490' },
	{ name: 'Slate', value: '#475569' },
]

export const DEFAULT_TAG_COLOR = TAG_COLORS[0].value

const HEX_COLOR = /^#[0-9a-f]{6}$/i

export function normalizeTagColor(color?: string | null): string {
	return color !== undefined && color !== null && HEX_COLOR.test(color)
		? color.toLowerCase()
		: DEFAULT_TAG_COLOR
}

function channelLuminance(channel: number): number {
	const value = channel / 255

	return value <= 0.03928
		? value / 12.92
		: ((value + 0.055) / 1.055) ** 2.4
}

export function tagTextColor(color?: string | null): string {
	const normalized = normalizeTagColor(color)
	const red = Number.parseInt(normalized.slice(1, 3), 16)
	const green = Number.parseInt(normalized.slice(3, 5), 16)
	const blue = Number.parseInt(normalized.slice(5, 7), 16)
	const luminance = 0.2126 * channelLuminance(red)
		+ 0.7152 * channelLuminance(green)
		+ 0.0722 * channelLuminance(blue)

	return luminance > 0.45 ? '#1a1a1a' : '#ffffff'
}

export function tagChipStyle(color?: string | null): Record<string, string> {
	const background = normalizeTagColor(color)

	return {
		backgroundColor: background,
		color: tagTextColor(background),
	}
}
