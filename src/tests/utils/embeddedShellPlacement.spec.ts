// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const flushPlacement = async() => {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const shell = () => document.querySelector('#profile-fields-personal-info-shell')

const renderShell = (layout: string) => {
	document.body.innerHTML = `
		${layout}
		<div id="profile-fields-personal-info-shell" class="personal-settings-setting-box profile-fields-personal-info-box">
			<div id="profile-fields-personal-info-settings"></div>
		</div>
	`
}

const prepare = async() => {
	const { prepareEmbeddedPersonalInfoShell } = await import('../../utils/embeddedShellPlacement')

	prepareEmbeddedPersonalInfoShell()
	await flushPlacement()
}

describe('embeddedShellPlacement', () => {
	beforeEach(() => {
		vi.resetModules()
		vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) => setTimeout(() => callback(0), 0) as unknown as number)
		vi.stubGlobal('cancelAnimationFrame', (handle: number) => clearTimeout(handle))
	})

	afterEach(() => {
		vi.unstubAllGlobals()
		document.body.innerHTML = ''
	})

	it('joins the single column section introduced in Nextcloud 35', async() => {
		renderShell('<div class="settings-column"><div class="settings-section"><div class="property-section"></div></div></div>')

		await prepare()

		const section = document.querySelector('.settings-column .settings-section')
		expect(shell()?.parentElement).toBe(section)
		expect(shell()).toBe(section?.lastElementChild)
		expect(shell()?.classList.contains('profile-fields-personal-info-box--column')).toBe(true)
		expect(shell()?.classList.contains('personal-settings-setting-box')).toBe(false)
	})

	it('waits for the core settings column to be rendered', async() => {
		renderShell('<div id="profile-contact-settings"></div>')

		await prepare()

		expect(shell()?.parentElement).toBe(document.body)

		const mountPoint = document.querySelector('#profile-contact-settings') as HTMLElement
		mountPoint.outerHTML = '<div class="settings-column"><div class="settings-section"></div></div>'
		await flushPlacement()

		expect(shell()?.parentElement).toBe(document.querySelector('.settings-section'))
	})

	it('joins the grid of the legacy personal info section', async() => {
		vi.stubGlobal('getComputedStyle', () => ({ gridTemplateColumns: '1fr 1fr' }))
		renderShell('<div id="personal-settings"><div id="personal-settings-group-container"></div></div>')

		await prepare()

		expect(shell()?.parentElement).toBe(document.querySelector('#personal-settings'))
		expect(shell()?.nextElementSibling).toBe(document.querySelector('#personal-settings-group-container'))
		expect(shell()?.classList.contains('personal-settings-setting-box')).toBe(true)
		expect(shell()?.classList.contains('profile-fields-personal-info-box--column')).toBe(false)
	})

	it('stacks above the visibility section of the legacy narrow layout', async() => {
		renderShell(`
			<div id="personal-settings"></div>
			<div class="personal-settings-section"><div id="profile-visibility"><div class="visibility-dropdowns"></div></div></div>
		`)

		await prepare()

		const visibilitySection = document.querySelector('#profile-visibility')?.closest('.personal-settings-section')
		expect(shell()?.nextElementSibling).toBe(visibilitySection)
		expect(shell()?.classList.contains('profile-fields-personal-info-box--stacked')).toBe(true)
		expect(visibilitySection?.querySelector('#profile-fields-personal-visibility-anchor')).not.toBeNull()
	})
})
