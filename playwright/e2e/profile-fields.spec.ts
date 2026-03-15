// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { createDefinition, deleteDefinitionByFieldKey } from '../support/profile-fields'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const collectEmbeddedLayoutMetrics = async(page, fieldKey: string) => {
	const aboutInput = page.getByRole('textbox', { name: 'About' })
	const customField = page.getByTestId(`profile-fields-personal-field-${fieldKey}`)
	const customInput = page.getByTestId(`profile-fields-personal-input-${fieldKey}`)
	const embeddedShell = page.locator('#profile-fields-personal-info-shell')

	await aboutInput.scrollIntoViewIfNeeded()
	await customInput.scrollIntoViewIfNeeded()

	const aboutBox = await aboutInput.boundingBox()
	const customFieldBox = await customField.boundingBox()
	const customInputBox = await customInput.boundingBox()
	const embeddedShellBox = await embeddedShell.boundingBox()

	expect(aboutBox).not.toBeNull()
	expect(customFieldBox).not.toBeNull()
	expect(customInputBox).not.toBeNull()
	expect(embeddedShellBox).not.toBeNull()

	return {
		aboutBox: aboutBox!,
		customFieldBox: customFieldBox!,
		customInputBox: customInputBox!,
		embeddedShellBox: embeddedShellBox!,
	}
}

const expectEmbeddedLayoutAtWidth = async(
	page,
	fieldKey: string,
	{
		width,
		expectExpandedShell,
	}: {
		width: number
		expectExpandedShell: boolean
	},
) => {
	await page.setViewportSize({ width, height: 1400 })
	await page.waitForTimeout(350)

	const { aboutBox, customFieldBox, customInputBox, embeddedShellBox } = await collectEmbeddedLayoutMetrics(page, fieldKey)
	const aboutBottom = aboutBox.y + aboutBox.height

	expect(Math.abs(aboutBox.width - customInputBox.width)).toBeLessThanOrEqual(6)
	expect(customFieldBox.y).toBeGreaterThanOrEqual(aboutBottom - 1)
	if (expectExpandedShell) {
		expect(embeddedShellBox.width).toBeGreaterThan(aboutBox.width + 100)
	}
}

test.beforeEach(async ({ page }) => {
	await login(page.request, adminUser, adminPassword)
})

test('admin can create, update, and delete a field definition', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_admin_${suffix}`
	const createdLabel = `Playwright admin ${suffix}`
	const updatedLabel = `Playwright admin updated ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)

	await page.goto('./settings/admin/profile_fields')
	await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

	await page.getByTestId('profile-fields-admin-new-field').click()
	await page.locator('#profile-fields-admin-field-key').fill(fieldKey)
	await page.locator('#profile-fields-admin-label').fill(createdLabel)
	await page.getByTestId('profile-fields-admin-save').click()

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field definition created.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toBeVisible()

	await page.locator('#profile-fields-admin-label').fill(updatedLabel)
	await page.getByTestId('profile-fields-admin-save').click()

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field definition updated.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toContainText(updatedLabel)

	await page.getByTestId('profile-fields-admin-delete').click()

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field definition deleted.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toHaveCount(0)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('embedded personal settings autosave a user-visible field', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_personal_${suffix}`
	const label = `Playwright personal ${suffix}`
	const value = `saved-${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	const definition = await createDefinition(page.request, {
		fieldKey,
		label,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'private',
	})

	try {
		await page.goto('./settings/user/personal-info')
		const fieldCard = page.getByTestId(`profile-fields-personal-field-${fieldKey}`)
		const fieldInput = page.getByTestId(`profile-fields-personal-input-${fieldKey}`)
		const visibilityPanel = page.getByRole('group', { name: 'Additional profile fields visibility' })
		const visibilityField = page.getByTestId(`profile-fields-personal-visibility-${fieldKey}`)
		await expect(page.getByTestId('profile-fields-personal')).toBeVisible()
		await expect(fieldCard).toBeVisible()
		await expect(fieldInput).toHaveAccessibleName(label)
		await expect(visibilityPanel).toBeVisible()
		await expect(visibilityField).toBeVisible()
		await expect(visibilityField).toContainText('Hide')

		await fieldInput.fill(value)

		await expect(fieldCard).toHaveAttribute('data-save-state', 'success')
		await expect(fieldCard.locator('span[aria-live="polite"]')).toContainText(`${label} saved.`)
		await expect(fieldCard).not.toContainText('Saved automatically.')

		await page.reload()
		await expect(fieldCard).toBeVisible()
		await expect(page.getByTestId(`profile-fields-personal-input-${fieldKey}`)).toHaveValue(value)
		await expect(page.getByTestId(`profile-fields-personal-visibility-${fieldKey}`)).toBeVisible()
		await expect(page.getByTestId(`profile-fields-personal-visibility-${fieldKey}`)).toContainText('Hide')

		for (const viewport of [
			{ width: 1400, expectExpandedShell: true },
			{ width: 1200, expectExpandedShell: true },
			{ width: 900, expectExpandedShell: true },
			{ width: 640, expectExpandedShell: false },
		]) {
			await expectEmbeddedLayoutAtWidth(page, fieldKey, viewport)
		}
	} finally {
		await deleteDefinitionByFieldKey(page.request, fieldKey)
	}
})
