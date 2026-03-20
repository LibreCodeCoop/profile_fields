// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import AdminUserFieldsDialog from '../../components/AdminUserFieldsDialog.vue'

vi.mock('@nextcloud/l10n', () => ({
	n: (_app: string, singular: string, plural: string, count: number, parameters?: Record<string, string | number>) => {
		const template = count === 1 ? singular : plural
		if (parameters === undefined) {
			return `tr:${template}`
		}

		return Object.entries(parameters).reduce((translated, [key, value]) => translated.replace(`{${key}}`, String(value)), `tr:${template}`)
	},
	t: (_app: string, text: string, parameters?: Record<string, string | number>) => {
		if (parameters === undefined) {
			return `tr:${text}`
		}

		return Object.entries(parameters).reduce((translated, [key, value]) => translated.replace(`{${key}}`, String(value)), `tr:${text}`)
	},
}))

vi.mock('../../api', () => ({
	listDefinitions: vi.fn().mockResolvedValue([
		{
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
	]),
	listAdminUserValues: vi.fn().mockResolvedValue([
		{
			id: 10,
			field_definition_id: 1,
			user_uid: 'alice',
			value: { value: '2026-03-20' },
			current_visibility: 'private',
			updated_by_uid: 'admin',
			updated_at: '2026-03-20T12:00:00+00:00',
		},
	]),
	upsertAdminUserValue: vi.fn(),
}))

vi.mock('@nextcloud/vue', () => ({
	NcButton: defineComponent({ template: '<button type="button"><slot /></button>' }),
	NcDialog: defineComponent({ template: '<div><slot /><slot name="actions" /></div>' }),
	NcEmptyContent: defineComponent({ template: '<div><slot /></div>' }),
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
	NcSelect: defineComponent({
		props: {
			options: { type: Array, default: () => [] },
		},
		template: '<select />',
	}),
}))

vi.mock('@nextcloud/vue/components/NcAvatar', () => ({
	default: defineComponent({ template: '<div />' }),
}))

describe('AdminUserFieldsDialog', () => {
	it('renders date fields with a native date input type', async() => {
		const wrapper = mount(AdminUserFieldsDialog, {
			props: {
				open: true,
				userUid: 'alice',
				userDisplayName: 'Alice',
			},
		})

		await flushPromises()

		const input = wrapper.find('#profile-fields-user-dialog-value-1')
		expect(input.exists()).toBe(true)
		expect(input.attributes('type')).toBe('date')
	})
})