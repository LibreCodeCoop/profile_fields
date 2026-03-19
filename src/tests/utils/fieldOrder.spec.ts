// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import { buildFieldOrderUpdates } from '../../utils/fieldOrder.ts'
import type { FieldDefinition } from '../../types'

const definition = (id: number, sortOrder: number): FieldDefinition => ({
	id,
	field_key: `field_${id}`,
	label: `Field ${id}`,
	type: 'text',
	edit_policy: 'users',
	exposure_policy: 'private',
	sort_order: sortOrder,
	active: true,
	options: null,
	created_at: '2026-03-10T00:00:00+00:00',
	updated_at: '2026-03-10T00:00:00+00:00',
})

describe('buildFieldOrderUpdates', () => {
	it('swaps with the previous definition and normalizes order', () => {
		const definitions = [definition(10, 4), definition(11, 7), definition(12, 9)]

		expect(buildFieldOrderUpdates(definitions, 11, -1)).toEqual([
			{ id: 11, sortOrder: 4 },
			{ id: 10, sortOrder: 5 },
			{ id: 12, sortOrder: 6 },
		])
	})

	it('returns no changes when already at the edge', () => {
		const definitions = [definition(10, 1), definition(11, 2)]

		expect(buildFieldOrderUpdates(definitions, 10, -1)).toEqual([])
		expect(buildFieldOrderUpdates(definitions, 11, 1)).toEqual([])
	})

	it('returns no changes when the selected definition is missing', () => {
		const definitions = [definition(10, 1), definition(11, 2)]

		expect(buildFieldOrderUpdates(definitions, 99, 1)).toEqual([])
	})
})
