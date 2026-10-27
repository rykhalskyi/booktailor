import { describe, expect, it } from 'vitest'
import { lineDiff } from '../../src/utils/diff'

describe('lineDiff', () => {
	it('marks unchanged, added, and removed lines', () => {
		const diff = lineDiff('one\ntwo\nthree', 'one\nthree\nfour')

		expect(diff).toEqual([
			{ type: 'same', text: 'one' },
			{ type: 'removed', text: 'two' },
			{ type: 'same', text: 'three' },
			{ type: 'added', text: 'four' },
		])
	})
})
