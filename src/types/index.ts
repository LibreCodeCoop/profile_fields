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

export type FieldType = ApiComponents['schemas']['Type'] | 'multiselect' | 'date' | 'boolean'
export type FieldVisibility = ApiComponents['schemas']['Visibility']
export type FieldEditPolicy = 'admins' | 'users'
export type FieldExposurePolicy = 'hidden' | FieldVisibility

type ApiFieldDefinition = ApiComponents['schemas']['Definition']

export type FieldDefinition = Omit<ApiFieldDefinition, 'admin_only' | 'user_editable' | 'user_visible' | 'initial_visibility'> & {
	edit_policy: FieldEditPolicy
	exposure_policy: FieldExposurePolicy
}

// openapi-typescript collapses the loose `value: mixed` schema to Record<string, never>.
// Keep the surrounding contract generated and widen only this payload leaf for frontend use.
export type FieldValuePayload = Omit<ApiComponents['schemas']['ValuePayload'], 'value'> & {
	value?: string | number | boolean | string[] | null
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

export type CreateDefinitionPayload = {
	fieldKey: string
	label: string
	type: FieldType
	editPolicy?: FieldEditPolicy
	exposurePolicy?: FieldExposurePolicy
	sortOrder?: number
	active?: boolean
	options?: string[]
}
export type UpdateDefinitionPayload = {
	label: string
	type: FieldType
	editPolicy?: FieldEditPolicy
	exposurePolicy?: FieldExposurePolicy
	sortOrder?: number
	active?: boolean
	options?: string[]
}
export type UpsertOwnValuePayload = Omit<ApiRequestJsonBody<ApiOperations['field_value_api-upsert']>, 'value'> & {
	value?: string | number | boolean | string[] | null
}
export type UpdateOwnVisibilityPayload = ApiRequestJsonBody<ApiOperations['field_value_api-update-visibility']>
export type UpsertAdminUserValuePayload = Omit<ApiRequestJsonBody<ApiOperations['field_value_admin_api-upsert']>, 'value'> & {
	value?: string | number | boolean | string[] | null
}

export type AdminEditableField = {
	definition: FieldDefinition
	value: FieldValueRecord | null
}

export type ApiError = {
	message: string
}

export const definitionDefaultVisibility = (definition: Pick<FieldDefinition, 'exposure_policy'>): FieldVisibility => {
	switch (definition.exposure_policy) {
	case 'public':
		return 'public'
	case 'users':
		return 'users'
	default:
		return 'private'
	}
}
