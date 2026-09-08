// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import 'vite/modulepreload-polyfill'

import { translate as t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import { prepareEmbeddedPersonalInfoShell } from './utils/embeddedShellPlacement'
import PersonalSettings from './views/PersonalSettings.vue'

const PERSONAL_INFO_SELECTOR = '#profile-fields-personal-info-settings'

const ensureEmbeddedProfileAnchor = () => {
	const profileSection = document.querySelector('#vue-profile-section section')
	if (profileSection === null || profileSection.querySelector('.profile-fields-personal__profile-anchor') !== null) {
		return
	}

	const anchor = document.createElement('a')
	anchor.className = 'profile-fields-personal__profile-anchor'
	anchor.href = '#profile-fields-personal-info'
	anchor.textContent = t('profile_fields', 'Additional profile fields')

	const referenceLink = profileSection.querySelector('a[href="#profile-visibility"]')
	if (referenceLink !== null && referenceLink.parentNode !== null) {
		referenceLink.parentNode.insertBefore(anchor, referenceLink.nextSibling)
	} else {
		profileSection.appendChild(anchor)
	}
}

const mountPersonalSettings = (selector: string) => {
	if (selector === PERSONAL_INFO_SELECTOR) {
		prepareEmbeddedPersonalInfoShell()
		ensureEmbeddedProfileAnchor()
	}

	const target = document.querySelector(selector)
	if (target === null) {
		return
	}

	const app = createApp(PersonalSettings)
	app.provide('profileFieldsEmbedded', selector === PERSONAL_INFO_SELECTOR)
	app.config.idPrefix = selector === PERSONAL_INFO_SELECTOR
		? 'profile-fields-personal-info'
		: 'profile-fields-personal'
	app.mount(target)
}

mountPersonalSettings('#profile-fields-personal-settings')
mountPersonalSettings(PERSONAL_INFO_SELECTOR)
