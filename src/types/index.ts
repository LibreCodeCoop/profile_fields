// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { components as ApiComponents, operations as ApiOperations } from './openapi/openapi-full'

type ApiJsonBody<TRequestBody> = TRequestBody extends {
	content: {
		'application/json': infer Body
	}
}
	? Body
	: never

type ApiOperationRequestBody<TOperation> = TOperation extends {
	requestBody?: infer RequestBody
}
	? NonNullable<RequestBody>
	: never

type ApiRequestJsonBody<TOperation> = ApiJsonBody<ApiOperationRequestBody<TOperation>>

export type FieldType = ApiComponents['schemas']['Type']
export type FieldVisibility = ApiComponents['schemas']['Visibility']
export type FieldDefinition = ApiComponents['schemas']['Definition']

// openapi-typescript collapses the loose `value: mixed` schema to Record<string, never>.
// Keep the surrounding contract generated and widen only this payload leaf for frontend use.
export type FieldValuePayload = Omit<ApiComponents['schemas']['ValuePayload'], 'value'> & {
	value?: string | number | null
}
export type FieldValueRecord = Omit<ApiComponents['schemas']['ValueRecord'], 'value'> & {
	value: FieldValuePayload
}
export type EditableField = Omit<ApiComponents['schemas']['EditableField'], 'definition' | 'value'> & {
	definition: FieldDefinition
	value: FieldValueRecord | null
}
export type LookupField = Omit<ApiComponents['schemas']['LookupField'], 'definition' | 'value'> & {
	definition: FieldDefinition
	value: FieldValueRecord
}
export type LookupResult = Omit<ApiComponents['schemas']['LookupResult'], 'fields'> & {
	fields: Record<string, LookupField>
}

export type CreateDefinitionPayload = ApiRequestJsonBody<ApiOperations['field_definition_api-create']>
export type UpdateDefinitionPayload = ApiRequestJsonBody<ApiOperations['field_definition_api-update']>
export type UpsertOwnValuePayload = ApiRequestJsonBody<ApiOperations['field_value_api-upsert']>
export type UpdateOwnVisibilityPayload = ApiRequestJsonBody<ApiOperations['field_value_api-update-visibility']>
export type UpsertAdminUserValuePayload = ApiRequestJsonBody<ApiOperations['field_value_admin_api-upsert']>

export type AdminEditableField = {
	definition: FieldDefinition
	value: FieldValueRecord | null
}

export type ApiError = {
	message: string
}
