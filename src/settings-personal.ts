// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import 'vite/modulepreload-polyfill'

import { translate as t } from '@nextcloud/l10n'
import { createApp } from 'vue'
import PersonalSettings from './views/PersonalSettings.vue'

const PERSONAL_INFO_SELECTOR = '#profile-fields-personal-info-settings'
const EMBEDDED_GRID_MIN_WIDTH = 1100

let embeddedShellPlacementFrame = 0
let embeddedShellResizeDebounceTimer = 0

const scheduleEmbeddedPersonalInfoShellPlacement = () => {
	if (embeddedShellPlacementFrame !== 0) {
		window.cancelAnimationFrame(embeddedShellPlacementFrame)
	}

	embeddedShellPlacementFrame = window.requestAnimationFrame(() => {
		embeddedShellPlacementFrame = 0
		syncEmbeddedPersonalInfoShellPlacement()
	})
}

const scheduleEmbeddedPersonalInfoShellPlacementAfterResize = () => {
	if (embeddedShellResizeDebounceTimer !== 0) {
		window.clearTimeout(embeddedShellResizeDebounceTimer)
	}

	embeddedShellResizeDebounceTimer = window.setTimeout(() => {
		embeddedShellResizeDebounceTimer = 0
		scheduleEmbeddedPersonalInfoShellPlacement()
	}, 140)
}

const syncEmbeddedPersonalInfoShellPlacement = () => {
	const shell = document.querySelector<HTMLElement>('#profile-fields-personal-info-shell')
	const personalSettings = document.querySelector<HTMLElement>('#personal-settings')
	const profileVisibilitySection = document.querySelector<HTMLElement>('#profile-visibility')?.closest('.personal-settings-section') as HTMLElement | null
	if (shell === null || personalSettings === null) {
		return
	}

	const useGridPlacement = personalSettings.getBoundingClientRect().width >= EMBEDDED_GRID_MIN_WIDTH
	personalSettings.classList.toggle('profile-fields-personal-info-grid', useGridPlacement)
	personalSettings.classList.toggle('profile-fields-personal-info-stacked', !useGridPlacement)
	shell.classList.toggle('personal-settings-setting-box', useGridPlacement)
	shell.classList.toggle('personal-settings-section', !useGridPlacement)
	shell.classList.toggle('profile-fields-personal-info-box--stacked', !useGridPlacement)

	if (useGridPlacement) {
		const insertionTarget = personalSettings.querySelector('#personal-settings-group-container')
			?? personalSettings.querySelector('.msg')

		if (insertionTarget !== null) {
			personalSettings.insertBefore(shell, insertionTarget)
		} else {
			personalSettings.appendChild(shell)
		}
	} else if (profileVisibilitySection !== null && profileVisibilitySection.parentNode !== null) {
		profileVisibilitySection.parentNode.insertBefore(shell, profileVisibilitySection)
	} else if (personalSettings.parentNode !== null) {
		personalSettings.parentNode.insertBefore(shell, personalSettings.nextSibling)
	}

	shell.style.marginTop = ''

	shell.dataset.profileFieldsEmbeddedReady = 'true'
}

const prepareEmbeddedPersonalInfoShell = () => {
	scheduleEmbeddedPersonalInfoShellPlacement()
	window.addEventListener('resize', scheduleEmbeddedPersonalInfoShellPlacementAfterResize, { passive: true })
	window.addEventListener('load', scheduleEmbeddedPersonalInfoShellPlacement, { once: true, passive: true })
}

const ensureEmbeddedProfileAnchor = () => {
	const profileSection = document.querySelector('#vue-profile-section section')
	if (profileSection === null || profileSection.querySelector('.profile-fields-personal__profile-anchor') !== null) {
		return
	}

	const anchor = document.createElement('a')
	anchor.className = 'profile-fields-personal__profile-anchor'
	anchor.href = '#profile-fields-personal-info'
	anchor.textContent = t('profile_fields', 'Edit your Profile fields')

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
