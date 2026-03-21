// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { FieldType } from '../types/index.ts'

export type WorkflowCheckValue = {
	field_key: string
	value: string | number | boolean | null
}

export type WorkflowCheckDefinition = {
	field_key: string
	label: string
	type: FieldType
	active: boolean
}

const textOperatorKeys = ['is-set', '!is-set', 'is', '!is', 'contains', '!contains'] as const
const numberOperatorKeys = ['is-set', '!is-set', 'is', '!is', 'less', '!greater', 'greater', '!less'] as const
const booleanOperatorKeys = ['is-set', '!is-set', 'is', '!is'] as const
const fallbackOperatorKeys = ['is-set', '!is-set', 'is', '!is', 'contains', '!contains', 'less', '!greater', 'greater', '!less'] as const

export const parseWorkflowCheckValue = (rawValue: string | null | undefined): WorkflowCheckValue | null => {
	if (typeof rawValue !== 'string' || rawValue.trim() === '') {
		return null
	}

	try {
		const parsed = JSON.parse(rawValue) as Partial<WorkflowCheckValue>
		if (typeof parsed.field_key !== 'string' || parsed.field_key.trim() === '') {
			return null
		}
		if (Array.isArray(parsed.value) || (typeof parsed.value === 'object' && parsed.value !== null)) {
			return null
		}

		return {
			field_key: parsed.field_key.trim(),
			value: parsed.value ?? null,
		}
	} catch {
		return null
	}
}

export const serializeWorkflowCheckValue = (value: WorkflowCheckValue): string => JSON.stringify({
	field_key: value.field_key,
	value: value.value,
})

export const findWorkflowDefinition = (rawValue: string | null | undefined, definitions: readonly WorkflowCheckDefinition[]): WorkflowCheckDefinition | null => {
	const parsed = parseWorkflowCheckValue(rawValue)
	if (parsed === null) {
		return null
	}

	return definitions.find((definition) => definition.active && definition.field_key === parsed.field_key) ?? null
}

export const getWorkflowOperatorKeys = (rawValue: string | null | undefined, definitions: readonly WorkflowCheckDefinition[]): string[] => {
	const definition = findWorkflowDefinition(rawValue, definitions)
	if (definition === null) {
		return [...fallbackOperatorKeys]
	}

	return definition.type === 'number'
		|| definition.type === 'date'
		? [...numberOperatorKeys]
		: definition.type === 'boolean'
			? [...booleanOperatorKeys]
		: definition.type === 'url' || definition.type === 'email'
			? [...textOperatorKeys]
		: [...textOperatorKeys]
}

export const isWorkflowOperatorSupported = (operator: string | null | undefined, rawValue: string | null | undefined, definitions: readonly WorkflowCheckDefinition[]): boolean => {
	if (typeof operator !== 'string' || operator.trim() === '') {
		return false
	}

	return getWorkflowOperatorKeys(rawValue, definitions).includes(operator)
}

export const workflowOperatorRequiresValue = (operator: string | null | undefined): boolean => operator !== 'is-set' && operator !== '!is-set'
