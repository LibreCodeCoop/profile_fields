// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import { COUNT_PLACEHOLDER, splitCountLabel } from '../../utils/countLabel.ts'

describe('splitCountLabel', () => {
	it('splits a label that starts with the count', () => {
		expect(splitCountLabel(`${COUNT_PLACEHOLDER} fields configured`)).toEqual({
			before: '',
			after: ' fields configured',
		})
	})

	it('splits a label that ends with the count, as right-to-left languages may order it', () => {
		expect(splitCountLabel(`حقول مكونة ${COUNT_PLACEHOLDER}`)).toEqual({
			before: 'حقول مكونة ',
			after: '',
		})
	})

	it('splits a label that places the count in the middle', () => {
		expect(splitCountLabel(`son ${COUNT_PLACEHOLDER} campos configurados`)).toEqual({
			before: 'son ',
			after: ' campos configurados',
		})
	})

	it('renders the count in front of the label when the translation dropped the placeholder', () => {
		expect(splitCountLabel('kolonky nastaveny')).toEqual({
			before: '',
			after: ' kolonky nastaveny',
		})
	})

	it('keeps the label empty when the translation is empty', () => {
		expect(splitCountLabel('')).toEqual({
			before: '',
			after: '',
		})
	})

	it('splits on the first placeholder only', () => {
		expect(splitCountLabel(`${COUNT_PLACEHOLDER} of ${COUNT_PLACEHOLDER}`)).toEqual({
			before: '',
			after: ` of ${COUNT_PLACEHOLDER}`,
		})
	})
})
