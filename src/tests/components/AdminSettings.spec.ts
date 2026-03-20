// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import AdminSettings from '../../views/AdminSettings.vue'

Object.defineProperty(window, 'matchMedia', {
	writable: true,
	value: vi.fn().mockImplementation(() => ({
		matches: false,
		addEventListener: vi.fn(),
		removeEventListener: vi.fn(),
	})),
})

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
	createDefinition: vi.fn(),
	deleteDefinition: vi.fn(),
	listDefinitions: vi.fn().mockResolvedValue([]),
	updateDefinition: vi.fn(),
}))

vi.mock('@nextcloud/vue', () => ({
	NcActionButton: defineComponent({ template: '<button type="button"><slot /><slot name="icon" /></button>' }),
	NcActions: defineComponent({ template: '<div><slot /><slot name="icon" /></div>' }),
	NcButton: defineComponent({
		emits: ['click'],
		template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
	}),
	NcCheckboxRadioSwitch: defineComponent({ template: '<div><slot /></div>' }),
	NcChip: defineComponent({ template: '<div><slot /></div>' }),
	NcEmptyContent: defineComponent({ template: '<div><slot /></div>' }),
	NcIconSvgWrapper: defineComponent({ template: '<span />' }),
	NcInputField: defineComponent({ template: '<input />' }),
	NcListItem: defineComponent({ template: '<div><slot /></div>' }),
	NcLoadingIcon: defineComponent({ template: '<div />' }),
	NcNoteCard: defineComponent({ template: '<div><slot /></div>' }),
	NcSelect: defineComponent({
		props: {
			options: { type: Array, default: () => [] },
		},
		template: '<div><span v-for="option in options" :key="option.value">{{ option.label }}</span></div>',
	}),
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: defineComponent({ template: '<div><slot /><slot name="actions" /></div>' }),
}))

vi.mock('../../components/AdminSupportBanner.vue', () => ({
	default: defineComponent({ template: '<div />' }),
}))

vi.mock('../../components/admin/AdminSelectOptionsSection.vue', () => ({
	default: defineComponent({ template: '<div />' }),
}))

describe('AdminSettings', () => {
	it('offers the Date field type in the editor', async() => {
		const wrapper = mount(AdminSettings, {
			global: {
				stubs: {
					Draggable: defineComponent({ template: '<div><slot /></div>' }),
				},
			},
		})

		await flushPromises()
		await wrapper.get('button').trigger('click')

		expect(wrapper.text()).toContain('tr:Date')
	})
})