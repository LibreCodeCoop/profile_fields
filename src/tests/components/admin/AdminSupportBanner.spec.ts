// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import AdminSupportBanner from '../../../components/AdminSupportBanner.vue'

vi.mock('@nextcloud/vue', () => ({
	NcButton: defineComponent({
		name: 'NcButton',
		emits: ['click'],
		template: '<button type="button" v-bind="$attrs" @click="$emit(\'click\', $event)"><slot /></button>',
	}),
	NcNoteCard: defineComponent({
		name: 'NcNoteCard',
		template: '<div><slot /></div>',
	}),
}))

afterEach(() => {
	window.localStorage.clear()
	vi.restoreAllMocks()
})

describe('AdminSupportBanner', () => {
	it('opens sponsor page when sponsor button is clicked', async() => {
		const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
		const wrapper = mount(AdminSupportBanner)

		await wrapper.get('button').trigger('click')

		expect(openSpy).toHaveBeenCalledWith('https://github.com/sponsors/LibreCodeCoop', '_blank', 'noopener,noreferrer')
	})

	it('hides itself after dismiss and persists state', async() => {
		const wrapper = mount(AdminSupportBanner)

		const buttons = wrapper.findAll('button')
		await buttons[1].trigger('click')

		expect(wrapper.find('[data-testid="profile-fields-admin-support-banner"]').exists()).toBe(false)
		expect(window.localStorage.getItem('profile_fields_support_banner_dismissed')).toBe('1')
	})

	it('starts hidden when dismissal key is already persisted', () => {
		window.localStorage.setItem('profile_fields_support_banner_dismissed', '1')

		const wrapper = mount(AdminSupportBanner)
		return nextTick().then(() => {
			expect(wrapper.find('[data-testid="profile-fields-admin-support-banner"]').exists()).toBe(false)
		})
	})
})
