const RELATIVE_UNITS: Array<[Intl.RelativeTimeFormatUnit, number]> = [
	['year', 365 * 24 * 60 * 60],
	['month', 30 * 24 * 60 * 60],
	['week', 7 * 24 * 60 * 60],
	['day', 24 * 60 * 60],
	['hour', 60 * 60],
	['minute', 60],
]

export function formatUpdatedAt(
	value: string,
	now: Date = new Date(),
	locale?: string,
): string {
	const date = new Date(value)
	if (Number.isNaN(date.getTime())) {
		return ''
	}

	const seconds = Math.round((date.getTime() - now.getTime()) / 1000)
	const absolute = Math.abs(seconds)

	if (absolute < 60) {
		return new Intl.RelativeTimeFormat(locale, { numeric: 'auto', style: 'short' })
			.format(seconds, 'second')
	}

	if (absolute >= 7 * 24 * 60 * 60) {
		return new Intl.DateTimeFormat(locale, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		}).format(date)
	}

	const formatter = new Intl.RelativeTimeFormat(locale, { numeric: 'auto', style: 'short' })
	for (const [unit, size] of RELATIVE_UNITS) {
		if (absolute >= size) {
			return formatter.format(Math.round(seconds / size), unit)
		}
	}

	return formatter.format(seconds, 'second')
}
