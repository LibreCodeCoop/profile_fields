// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import PersonalSettings from '../../views/PersonalSettings.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app: string, text: string, parameters?: Record<string, string | number>) => {
		if (parameters === undefined) {
			return `tr:${text}`
		}

		return Object.entries(parameters).reduce((translated, [key, value]) => translated.replace(`{${key}}`, String(value)), `tr:${text}`)
	},
}))

vi.mock('../../api', () => ({
	listEditableFields: vi.fn().mockResolvedValue([
		{
			definition: {
				id: 1,
				field_key: 'start_date',
				label: 'Start date',
				type: 'date',
				edit_policy: 'users',
				exposure_policy: 'private',
				sort_order: 0,
				active: true,
				options: null,
			},
			value: {
				id: 10,
				field_definition_id: 1,
				user_uid: 'alice',
				value: { value: '2026-03-20' },
				current_visibility: 'private',
				updated_by_uid: 'alice',
				updated_at: '2026-03-20T12:00:00+00:00',
			},
			can_edit: true,
		},
		{
			definition: {
				id: 2,
				field_key: 'is_manager',
				label: 'Is manager',
				type: 'boolean',
				edit_policy: 'users',
				exposure_policy: 'private',
				sort_order: 1,
				active: true,
				options: null,
			},
			value: {
				id: 11,
				field_definition_id: 2,
				user_uid: 'alice',
				value: { value: true },
				current_visibility: 'private',
				updated_by_uid: 'alice',
				updated_at: '2026-03-20T12:00:00+00:00',
			},
			can_edit: true,
		},
		{
			definition: {
				id: 5,
				field_key: 'website',
				label: 'Website',
				type: 'url',
				edit_policy: 'users',
				exposure_policy: 'private',
				sort_order: 2,
				active: true,
				options: null,
			},
			value: {
				id: 12,
				field_definition_id: 5,
				user_uid: 'alice',
				value: { value: 'https://example.com' },
				current_visibility: 'private',
				updated_by_uid: 'alice',
				updated_at: '2026-03-20T12:00:00+00:00',
			},
			can_edit: true,
		},
	]),
	upsertOwnValue: vi.fn(),
}))

vi.mock('../../types', async() => {
	const actual = await vi.importActual<typeof import('../../types')>('../../types')
	return actual
})

vi.mock('../../utils/visibilityOptions.js', async() => {
	const actual = await vi.importActual<typeof import('../../utils/visibilityOptions.js')>('../../utils/visibilityOptions.js')
	return actual
})

vi.mock('@nextcloud/vue', () => ({
	NcButton: defineComponent({ template: '<button type="button"><slot /></button>' }),
	NcEmptyContent: defineComponent({ template: '<div><slot /></div>' }),
	NcIconSvgWrapper: defineComponent({ template: '<span />' }),
	NcInputField: defineComponent({
		inheritAttrs: false,
		props: {
			id: { type: String, default: '' },
			type: { type: String, default: 'text' },
			inputmode: { type: String, default: 'text' },
			modelValue: { type: [String, Number], default: '' },
		},
		template: '<input v-bind="$attrs" :id="id" :type="type" :inputmode="inputmode" :value="modelValue" />',
	}),
	NcLoadingIcon: defineComponent({ template: '<div />' }),
	NcNoteCard: defineComponent({ template: '<div><slot /></div>' }),
	NcPopover: defineComponent({ template: '<div><slot /><slot name="trigger" :attrs="{}" /></div>' }),
	NcSelect: defineComponent({
		props: {
			options: { type: Array, default: () => [] },
		},
		template: '<div><span v-for="option in options" :key="String(option.value)">{{ option.label }}</span></div>',
	}),
}))

describe('PersonalSettings', () => {
	it('renders date fields with a native date input type', async() => {
		const wrapper = mount(PersonalSettings, {
			global: {
				stubs: {
					Teleport: true,
				},
			},
		})

		await flushPromises()

		const input = wrapper.find('[data-testid="profile-fields-personal-input-start_date"]')
		expect(input.exists()).toBe(true)
		expect(input.attributes('type')).toBe('date')
	})

	it('renders boolean fields with true and false options', async() => {
		const wrapper = mount(PersonalSettings, {
			global: {
				stubs: {
					Teleport: true,
				},
			},
		})

		await flushPromises()

		expect(wrapper.text()).toContain('tr:True')
		expect(wrapper.text()).toContain('tr:False')
	})

	it('renders url fields with type=url input', async() => {
		const wrapper = mount(PersonalSettings, {
			global: {
				stubs: {
					Teleport: true,
				},
			},
		})

		await flushPromises()

		const input = wrapper.find('[data-testid="profile-fields-personal-input-website"]')
		expect(input.exists()).toBe(true)
		expect(input.attributes('type')).toBe('url')
	})
})