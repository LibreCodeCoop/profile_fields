import type { AdminEditableField, FieldDefinition, FieldValueRecord } from '../types'

export const buildAdminEditableFields = (
	definitions: FieldDefinition[],
	values: FieldValueRecord[],
): AdminEditableField[] => {
	const valuesByDefinitionId = new Map(values.map((value) => [value.field_definition_id, value]))

	return [...definitions]
		.filter((definition) => definition.active)
		.sort((left, right) => left.sort_order - right.sort_order || left.id - right.id)
		.map((definition) => ({
			definition,
			value: valuesByDefinitionId.get(definition.id) ?? null,
		}))
}
