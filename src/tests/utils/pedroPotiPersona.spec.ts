// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it } from 'vitest'

import { pedroPotiPersona } from '../../utils/pedroPotiPersona.js'

describe('pedroPotiPersona', () => {
	it('keeps the demo identity and biography aligned with Pedro Poti in English', () => {
		expect(pedroPotiPersona.user.displayName).toBe('Pedro Poti')
		expect(pedroPotiPersona.user.email).toBe('pedro.poti@example.net')

		const accountFieldByLabel = new Map(pedroPotiPersona.accountFields.map((field) => [field.label, field.value]))
		expect(accountFieldByLabel.get('Location')).toBe('Massurepe, Paraiba')
		expect(accountFieldByLabel.get('Role')).toBe('Regedor of Paraiba')
		expect(accountFieldByLabel.get('Headline')).toContain('Dutch Brazil')
		expect(accountFieldByLabel.get('About')).toContain('five years in the Netherlands')
		expect(accountFieldByLabel.get('About')).toContain('Reformed faith')
		expect(accountFieldByLabel.get('About')).toContain('Paraiba chamber')
	})

	it('replaces generic showcase metadata with persona-specific profile fields', () => {
		expect(pedroPotiPersona.showcaseFields.map((field) => field.label)).toEqual([
			'Territory',
			'Core strength',
			'Community',
			'Letter alias',
			'Leadership role',
			'Council channel',
			'Council rank',
		])

		const showcaseValueByKey = new Map(pedroPotiPersona.showcaseFields.map((field) => [field.fieldKey, field.demoValue.value]))
		expect(showcaseValueByKey.get('showcase_support_region')).toBe('Captaincy of Paraiba')
		expect(showcaseValueByKey.get('showcase_product_specialty')).toBe('Tupi correspondence')
		expect(showcaseValueByKey.get('showcase_customer_segment')).toBe('Potiguara communities')
		expect(showcaseValueByKey.get('showcase_incident_role')).toBe('Regedor of Paraiba')
		expect(showcaseValueByKey.get('showcase_council_channel')).toBe('Village assembly')
	})
})
