// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import {
	getWorkflowOperatorKeys,
	isWorkflowOperatorSupported,
	parseWorkflowCheckValue,
	serializeWorkflowCheckValue,
} from '../../utils/workflowProfileFieldCheck.ts'

const definitions = [
	{ field_key: 'region', label: 'Region', type: 'text', active: true },
	{ field_key: 'score', label: 'Score', type: 'number', active: true },
	{ field_key: 'start_date', label: 'Start date', type: 'date', active: true },
	{ field_key: 'is_manager', label: 'Is manager', type: 'boolean', active: true },
	{ field_key: 'website', label: 'Website', type: 'url', active: true },
] as const

describe('workflowProfileFieldCheck', () => {
	it('serializes and parses workflow values consistently', () => {
		const encoded = serializeWorkflowCheckValue({ field_key: 'region', value: 'LATAM' })

		expect(parseWorkflowCheckValue(encoded)).toEqual({ field_key: 'region', value: 'LATAM' })
	})

	it('returns text operators for text definitions', () => {
		expect(getWorkflowOperatorKeys(serializeWorkflowCheckValue({ field_key: 'region', value: 'LATAM' }), definitions)).toEqual([
			'is-set',
			'!is-set',
			'is',
			'!is',
			'contains',
			'!contains',
		])
	})

	it('returns numeric operators for number definitions', () => {
		expect(getWorkflowOperatorKeys(serializeWorkflowCheckValue({ field_key: 'score', value: '9' }), definitions)).toEqual([
			'is-set',
			'!is-set',
			'is',
			'!is',
			'less',
			'!greater',
			'greater',
			'!less',
		])
	})

	it('returns numeric-style comparison operators for date definitions', () => {
		expect(getWorkflowOperatorKeys(serializeWorkflowCheckValue({ field_key: 'start_date', value: '2026-03-20' }), definitions)).toEqual([
			'is-set',
			'!is-set',
			'is',
			'!is',
			'less',
			'!greater',
			'greater',
			'!less',
		])
	})

	it('returns exact-match operators for boolean definitions', () => {
		expect(getWorkflowOperatorKeys(serializeWorkflowCheckValue({ field_key: 'is_manager', value: true }), definitions)).toEqual([
			'is-set',
			'!is-set',
			'is',
			'!is',
		])
	})

	it('rejects unsupported operators for the selected field type', () => {
		expect(isWorkflowOperatorSupported('contains', serializeWorkflowCheckValue({ field_key: 'score', value: '9' }), definitions)).toBe(false)
		expect(isWorkflowOperatorSupported('greater', serializeWorkflowCheckValue({ field_key: 'score', value: '9' }), definitions)).toBe(true)
	})

	it('returns text-style operators for url definitions', () => {
		expect(getWorkflowOperatorKeys(serializeWorkflowCheckValue({ field_key: 'website', value: 'https://example.com' }), definitions)).toEqual([
			'is-set',
			'!is-set',
			'is',
			'!is',
			'contains',
			'!contains',
		])
	})

	it('accepts contains operator for url field', () => {
		expect(isWorkflowOperatorSupported('contains', serializeWorkflowCheckValue({ field_key: 'website', value: 'example.com' }), definitions)).toBe(true)
	})
})
