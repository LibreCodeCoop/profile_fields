// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { APIRequestContext } from '@playwright/test'

type FieldType = 'text' | 'number'
type FieldVisibility = 'private' | 'users' | 'public'

type FieldDefinition = {
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

type OcsEnvelope<T> = {
	ocs: {
		meta: {
			status: string
			statuscode: number
			message: string
		}
		data: T
	}
}

type DefinitionPayload = {
	fieldKey: string
	label: string
	type?: FieldType
	adminOnly?: boolean
	userEditable?: boolean
	userVisible?: boolean
	initialVisibility?: FieldVisibility
	sortOrder?: number
	active?: boolean
}

async function appRequest<T>(
	request: APIRequestContext,
	method: 'GET' | 'POST' | 'PUT' | 'DELETE',
	path: string,
	body?: unknown,
): Promise<T> {
	const headers: Record<string, string> = {
		'OCS-APIRequest': 'true',
		Accept: 'application/json',
	}

	if (body !== undefined) {
		headers['Content-Type'] = 'application/json'
	}

	const url = `./ocs/v2.php/apps/profile_fields${path}`
	const options = {
		headers,
		data: body,
		failOnStatusCode: false,
	}

	const response = method === 'GET'
		? await request.get(url, options)
		: method === 'POST'
			? await request.post(url, options)
			: method === 'PUT'
				? await request.put(url, options)
				: await request.delete(url, options)

	const rawBody = await response.text()
	let parsed: OcsEnvelope<T>

	try {
		parsed = JSON.parse(rawBody) as OcsEnvelope<T>
	} catch {
		throw new Error(`Unexpected ${method} ${path} response: ${response.status()} ${rawBody}`)
	}

	if (!response.ok() || parsed.ocs.meta.status !== 'ok') {
		throw new Error(`${method} ${path} failed: ${parsed.ocs.meta.message || response.statusText()}`)
	}

	return parsed.ocs.data
}

export async function listDefinitions(request: APIRequestContext): Promise<FieldDefinition[]> {
	return await appRequest<FieldDefinition[]>(request, 'GET', '/api/v1/definitions')
}

export async function createDefinition(
	request: APIRequestContext,
	payload: DefinitionPayload,
): Promise<FieldDefinition> {
	return await appRequest<FieldDefinition>(request, 'POST', '/api/v1/definitions', {
		fieldKey: payload.fieldKey,
		label: payload.label,
		type: payload.type ?? 'text',
		adminOnly: payload.adminOnly ?? false,
		userEditable: payload.userEditable ?? true,
		userVisible: payload.userVisible ?? true,
		initialVisibility: payload.initialVisibility ?? 'private',
		sortOrder: payload.sortOrder ?? 0,
		active: payload.active ?? true,
	})
}

export async function deleteDefinition(request: APIRequestContext, id: number): Promise<void> {
	await appRequest<null>(request, 'DELETE', `/api/v1/definitions/${id}`)
}

export async function deleteDefinitionByFieldKey(request: APIRequestContext, fieldKey: string): Promise<void> {
	const definitions = await listDefinitions(request)
	const definition = definitions.find((candidate) => candidate.field_key === fieldKey)

	if (definition !== undefined) {
		await deleteDefinition(request, definition.id)
	}
}
