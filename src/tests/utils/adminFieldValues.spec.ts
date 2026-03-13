import { describe, expect, it } from 'vitest'

import { buildAdminEditableFields } from '../../utils/adminFieldValues.ts'
import type { FieldDefinition, FieldValueRecord } from '../../types'

const definition = (id: number, sortOrder: number, active = true): FieldDefinition => ({
	id,
	field_key: `field_${id}`,
	label: `Field ${id}`,
	type: 'text',
	admin_only: false,
	user_editable: true,
	user_visible: true,
	initial_visibility: 'private',
	sort_order: sortOrder,
	active,
	created_at: '2026-03-10T00:00:00+00:00',
	updated_at: '2026-03-10T00:00:00+00:00',
})

const value = (fieldDefinitionId: number, userUid = 'alice'): FieldValueRecord => ({
	id: fieldDefinitionId + 100,
	field_definition_id: fieldDefinitionId,
	user_uid: userUid,
	value: { value: `value-${fieldDefinitionId}` },
	current_visibility: 'users',
	updated_by_uid: 'admin',
	updated_at: '2026-03-10T00:00:00+00:00',
})

describe('buildAdminEditableFields', () => {
	it('returns active definitions sorted with matching values', () => {
		const result = buildAdminEditableFields([
			definition(11, 3),
			definition(10, 1),
			definition(12, 2, false),
		], [value(11)])

		expect(result.map((entry) => entry.definition.id)).toEqual([10, 11])
		expect(result[0].value).toBeNull()
		expect(result[1].value?.field_definition_id).toBe(11)
	})

	it('tolerates unrelated values', () => {
		const result = buildAdminEditableFields([
			definition(10, 1),
		], [value(99)])

		expect(result[0].value).toBeNull()
	})
})
