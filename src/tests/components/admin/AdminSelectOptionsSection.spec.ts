// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import AdminSelectOptionsSection from '../../../components/admin/AdminSelectOptionsSection.vue'

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

vi.mock('@nextcloud/vue', () => ({
	NcActionButton: defineComponent({ template: '<div><slot /><slot name="icon" /></div>' }),
	NcActions: defineComponent({ template: '<div><slot /><slot name="icon" /></div>' }),
	NcButton: defineComponent({
		name: 'NcButton',
		emits: ['click'],
		template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
	}),
	NcIconSvgWrapper: defineComponent({ template: '<div />' }),
	NcInputField: defineComponent({ template: '<div />' }),
}))

vi.mock('@nextcloud/vue/components/NcDialog', () => ({
	default: defineComponent({ template: '<div><slot /><slot name="actions" /></div>' }),
}))

vi.mock('@nextcloud/vue/components/NcTextArea', () => ({
	default: defineComponent({ template: '<textarea />' }),
}))

const DraggableStub = defineComponent({
	name: 'Draggable',
	props: {
		modelValue: {
			type: Array,
			required: true,
		},
	},
	template: '<div><slot v-for="(item, index) in modelValue" :key="item.id" :element="item" :index="index" /></div>',
})

describe('AdminSelectOptionsSection', () => {
	it('renders translated headings and pluralized meta', () => {
		const wrapper = mount(AdminSelectOptionsSection, {
			props: {
				modelValue: [{ id: 'option-0', value: 'Alpha' }],
				isSaving: false,
			},
			global: {
				stubs: {
					Draggable: DraggableStub,
					NcDialog: false,
					NcTextArea: false,
					NcActionButton: false,
					NcActions: false,
					NcIconSvgWrapper: false,
					NcInputField: false,
				},
			},
		})

		expect(wrapper.text()).toContain('tr:Options')
		expect(wrapper.text()).toContain('tr:Option')
		expect(wrapper.text()).toContain('tr:Add single option')
	})

	it('emits updated model when adding a new option', async() => {
		const wrapper = mount(AdminSelectOptionsSection, {
			props: {
				modelValue: [{ id: 'option-0', value: 'Alpha' }],
				isSaving: false,
			},
			global: {
				stubs: {
					Draggable: DraggableStub,
					NcDialog: false,
					NcTextArea: false,
					NcActionButton: false,
					NcActions: false,
					NcIconSvgWrapper: false,
					NcInputField: false,
				},
			},
		})

		const addButton = wrapper.find('[data-testid="profile-fields-admin-add-option"]')
		await addButton.trigger('click')

		const emissions = wrapper.emitted('update:modelValue')
		expect(emissions).toBeTruthy()
		expect((emissions as any[])[0][0]).toHaveLength(2)
		expect((emissions as any[])[0][0][1].value).toBe('')
	})

	it('does not emit add when there is already an empty option', async() => {
		const wrapper = mount(AdminSelectOptionsSection, {
			props: {
				modelValue: [
					{ id: 'option-0', value: 'Alpha' },
					{ id: 'option-1', value: '' },
				],
				isSaving: false,
			},
			global: {
				stubs: {
					Draggable: DraggableStub,
					NcDialog: false,
					NcTextArea: false,
					NcActionButton: false,
					NcActions: false,
					NcIconSvgWrapper: false,
					NcInputField: false,
				},
			},
		})

		const addButton = wrapper.find('[data-testid="profile-fields-admin-add-option"]')
		await addButton.trigger('click')

		expect(wrapper.emitted('update:modelValue')).toBeUndefined()
	})
})
