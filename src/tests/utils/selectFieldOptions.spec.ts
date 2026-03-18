// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import {
	createEditableSelectOptions,
	extractEditableSelectOptionValues,
	normalizeEditableSelectOptionValue,
	parseEditableSelectOptionValues,
	moveEditableSelectOption,
} from '../../utils/selectFieldOptions.ts'

describe('selectFieldOptions', () => {
	it('creates draggable options with stable ids and preserves values', () => {
		let nextId = 0
		const createId = () => `option-${nextId++}`

		expect(createEditableSelectOptions(['Alpha', 'Beta'], createId)).toEqual([
			{ id: 'option-0', value: 'Alpha' },
			{ id: 'option-1', value: 'Beta' },
		])
	})

	it('extracts option values for persistence', () => {
		expect(extractEditableSelectOptionValues([
			{ id: 'option-0', value: 'Alpha' },
			{ id: 'option-1', value: 'Beta' },
		])).toEqual(['Alpha', 'Beta'])
	})

	it('normalizes option values for duplicate detection', () => {
		expect(normalizeEditableSelectOptionValue('  Alpha  ')).toBe('alpha')
		expect(normalizeEditableSelectOptionValue('BeTa')).toBe('beta')
	})

	it('parses multiple option lines and ignores blanks', () => {
		expect(parseEditableSelectOptionValues('Alpha\n\n Beta \n  \nGamma')).toEqual(['Alpha', 'Beta', 'Gamma'])
	})

	it('moves an option one position up or down', () => {
		const options = [
			{ id: 'option-0', value: 'Alpha' },
			{ id: 'option-1', value: 'Beta' },
			{ id: 'option-2', value: 'Gamma' },
		]

		expect(moveEditableSelectOption(options, 1, -1).map(({ id }) => id)).toEqual(['option-1', 'option-0', 'option-2'])
		expect(moveEditableSelectOption(options, 1, 1).map(({ id }) => id)).toEqual(['option-0', 'option-2', 'option-1'])
	})

	it('returns the original array when the move is out of bounds', () => {
		const options = [
			{ id: 'option-0', value: 'Alpha' },
			{ id: 'option-1', value: 'Beta' },
		]

		expect(moveEditableSelectOption(options, 0, -1)).toBe(options)
		expect(moveEditableSelectOption(options, 1, 1)).toBe(options)
	})
})
