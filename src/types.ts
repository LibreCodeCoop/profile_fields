// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

export type FieldType = 'text' | 'number'

export type FieldVisibility = 'private' | 'users' | 'public'

export interface FieldDefinition {
	id: number
	field_key: string
	label: string
	type: FieldType
	admin_only: boolean
	user_editable: boolean
	user_visible: boolean
	initial_visibility: FieldVisibility
	sort_order: number
	active: boolean
	created_at: string
	updated_at: string
}

export interface FieldValuePayload {
	value?: string | number | null
}

export interface FieldValueRecord {
	id: number
	field_definition_id: number
	user_uid: string
	value: FieldValuePayload
	current_visibility: FieldVisibility
	updated_by_uid: string
	updated_at: string
}

export interface EditableField {
	definition: FieldDefinition
	value: FieldValueRecord | null
	can_edit: boolean
}

export interface AdminEditableField {
	definition: FieldDefinition
	value: FieldValueRecord | null
}

export interface ApiError {
	message: string
}
