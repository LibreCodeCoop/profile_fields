// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { t } from '@nextcloud/l10n'
import { createApp, h, reactive } from 'vue'
import AdminUserFieldsDialog from './components/AdminUserFieldsDialog.vue'

const state = reactive({
	open: false,
	userUid: '',
	userDisplayName: '',
})

const mountDialog = () => {
	if (document.getElementById('profile-fields-user-management-action-root') !== null) {
		return
	}

	const target = document.createElement('div')
	target.id = 'profile-fields-user-management-action-root'
	document.body.appendChild(target)

	createApp({
		render: () => h(AdminUserFieldsDialog, {
			open: state.open,
			userUid: state.userUid,
			userDisplayName: state.userDisplayName,
			'onUpdate:open': (value: boolean) => {
				state.open = value
				if (!value) {
					state.userUid = ''
					state.userDisplayName = ''
				}
			},
		}),
	}).mount(target)
}

const openDialog = (_event: Event, user: SettingsUserListRow) => {
	if (!user.id) {
		return
	}

	mountDialog()
	state.userUid = user.id
	state.userDisplayName = typeof user.displayname === 'string' && user.displayname.trim() !== '' ? user.displayname : user.id
	state.open = true
}

const registerAction = (attempt = 0) => {
	if (window.__profileFieldsUserActionRegistered) {
		return
	}

	const register = window.OCA?.Settings?.UserList?.registerAction
	if (typeof register !== 'function') {
		if (attempt < 200) {
			window.setTimeout(() => registerAction(attempt + 1), 50)
		}
		return
	}

	mountDialog()
	register(
		'icon-user',
		t('profile_fields', 'Edit additional profile fields'),
		openDialog,
		(user) => typeof user.id === 'string' && user.id.length > 0,
	)
	window.__profileFieldsUserActionRegistered = true
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => registerAction(), { once: true })
} else {
	registerAction()
}
