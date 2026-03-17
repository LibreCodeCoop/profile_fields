// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import './polyfills/buffer.js'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import type {
	CreateDefinitionPayload,
	EditableField,
	FieldDefinition,
	FieldValueRecord,
	UpdateDefinitionPayload,
	UpdateOwnVisibilityPayload,
	UpsertAdminUserValuePayload,
	UpsertOwnValuePayload,
} from './types'

export type WorkflowTargetSuggestion = {
	token: string
	label: string
	description: string
}

const jsonHeaders = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
	'Content-Type': 'application/json',
}

const apiUrl = (path: string) => generateOcsUrl(`/apps/profile_fields${path}`)

export const listDefinitions = async(): Promise<FieldDefinition[]> => {
	const response = await axios.get<{ ocs: { data: FieldDefinition[] } }>(apiUrl('/api/v1/definitions'), {
		headers: { 'OCS-APIRequest': 'true' },
	})
	return response.data.ocs.data
}

export const createDefinition = async(payload: CreateDefinitionPayload): Promise<FieldDefinition> => {
	const response = await axios.post<{ ocs: { data: FieldDefinition } }>(apiUrl('/api/v1/definitions'), payload, {
		headers: jsonHeaders,
	})
	return response.data.ocs.data
}

export const updateDefinition = async(id: number, payload: UpdateDefinitionPayload): Promise<FieldDefinition> => {
	const response = await axios.put<{ ocs: { data: FieldDefinition } }>(apiUrl(`/api/v1/definitions/${id}`), payload, {
		headers: jsonHeaders,
	})
	return response.data.ocs.data
}

export const deleteDefinition = async(id: number): Promise<void> => {
	await axios.delete(apiUrl(`/api/v1/definitions/${id}`), {
		headers: { 'OCS-APIRequest': 'true' },
	})
}

export const listEditableFields = async(): Promise<EditableField[]> => {
	const response = await axios.get<{ ocs: { data: EditableField[] } }>(apiUrl('/api/v1/me/values'), {
		headers: { 'OCS-APIRequest': 'true' },
	})
	return response.data.ocs.data
}

export const upsertOwnValue = async(fieldDefinitionId: number, payload: UpsertOwnValuePayload): Promise<FieldValueRecord> => {
	const response = await axios.put<{ ocs: { data: FieldValueRecord } }>(apiUrl(`/api/v1/me/values/${fieldDefinitionId}`), payload, {
		headers: jsonHeaders,
	})
	return response.data.ocs.data
}

export const updateOwnVisibility = async(fieldDefinitionId: number, currentVisibility: UpdateOwnVisibilityPayload['currentVisibility']): Promise<FieldValueRecord> => {
	const payload: UpdateOwnVisibilityPayload = {
		currentVisibility,
	}
	const response = await axios.put<{ ocs: { data: FieldValueRecord } }>(apiUrl(`/api/v1/me/values/${fieldDefinitionId}/visibility`), payload, {
		headers: jsonHeaders,
	})
	return response.data.ocs.data
}

export const listAdminUserValues = async(userUid: string): Promise<FieldValueRecord[]> => {
	const response = await axios.get<{ ocs: { data: FieldValueRecord[] } }>(apiUrl(`/api/v1/users/${encodeURIComponent(userUid)}/values`), {
		headers: { 'OCS-APIRequest': 'true' },
	})
	return response.data.ocs.data
}

export const upsertAdminUserValue = async(userUid: string, fieldDefinitionId: number, payload: UpsertAdminUserValuePayload): Promise<FieldValueRecord> => {
	const response = await axios.put<{ ocs: { data: FieldValueRecord } }>(apiUrl(`/api/v1/users/${encodeURIComponent(userUid)}/values/${fieldDefinitionId}`), payload, {
		headers: jsonHeaders,
	})
	return response.data.ocs.data
}

export const searchWorkflowTargetSuggestions = async(search: string, limit = 5): Promise<WorkflowTargetSuggestion[]> => {
	const trimmedSearch = search.trim()
	const [groupsResponse, usersResponse] = await Promise.all([
		axios.get<{ ocs: { data: { groups?: Array<{ id: string, displayname?: string }> } } }>(
			generateOcsUrl('/cloud/groups/details?search={search}&offset={offset}&limit={limit}', { search: trimmedSearch, offset: 0, limit }),
			{ headers: { 'OCS-APIRequest': 'true' } },
		),
		axios.get<{ ocs: { data: { users?: Record<string, { id?: string, displayname?: string }> } } }>(
			generateOcsUrl('/cloud/users/details?offset={offset}&limit={limit}&search={search}', { offset: 0, limit, search: trimmedSearch }),
			{ headers: { 'OCS-APIRequest': 'true' } },
		),
	])

	const groups = (groupsResponse.data.ocs?.data?.groups ?? []).map((group) => ({
		token: `group:${group.id}`,
		label: group.displayname?.trim() || group.id,
		description: `Group: ${group.id}`,
	}))

	const users = Object.entries(usersResponse.data.ocs?.data?.users ?? {}).map(([uid, user]) => ({
		token: `user:${uid}`,
		label: user.displayname?.trim() || uid,
		description: `User: ${uid}`,
	}))

	return [...groups, ...users]
}
