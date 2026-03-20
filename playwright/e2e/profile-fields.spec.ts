// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { createDefinition, deleteDefinitionByFieldKey } from '../support/profile-fields'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const optionInput = (page, index: number) => page.getByTestId(`profile-fields-admin-option-row-${index}`).locator('input')

const chooseFieldType = async(page, label: 'Text' | 'Number' | 'Select') => {
	await page.getByTestId('profile-fields-admin-type-select').click()
	await page.getByRole('option', { name: label, exact: true }).click()
}

const openSelectDefinitionEditor = async(page, fieldKey: string, label: string) => {
	await page.goto('./settings/admin/profile_fields')
	await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

	await page.getByTestId(`profile-fields-admin-definition-${fieldKey}`).click()
	await expect(page.locator('#profile-fields-admin-label')).toHaveValue(label)
	await expect(page.getByTestId('profile-fields-admin-option-row-0')).toBeVisible()
}

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

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field created successfully.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toBeVisible()

	await page.locator('#profile-fields-admin-label').fill(updatedLabel)
	await page.getByTestId('profile-fields-admin-save').click()

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field updated successfully.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toContainText(updatedLabel)

	await page.getByTestId('profile-fields-admin-delete').click()

	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field deleted successfully.')
	await expect(page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)).toHaveCount(0)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin uses a modal editor on compact layout', async ({ page }) => {
	const suffix = Date.now()
	const existingFieldKey = `playwright_mobile_existing_${suffix}`
	const existingLabel = `Playwright mobile existing ${suffix}`
	const createdFieldKey = `playwright_mobile_created_${suffix}`
	const createdLabel = `Playwright mobile created ${suffix}`

	await deleteDefinitionByFieldKey(page.request, existingFieldKey)
	await deleteDefinitionByFieldKey(page.request, createdFieldKey)
	await createDefinition(page.request, {
		fieldKey: existingFieldKey,
		label: existingLabel,
	})

	try {
		await page.setViewportSize({ width: 768, height: 1180 })
		await page.goto('./settings/admin/profile_fields')
		await expect(page.getByTestId('profile-fields-admin')).toBeVisible()
		await expect(page.getByText('No field selected')).toHaveCount(0)

		await page.getByTestId(`profile-fields-admin-definition-${existingFieldKey}`).click()
		const editDialog = page.getByRole('dialog', { name: 'Edit field' })
		await expect(editDialog).toBeVisible()
		await expect(editDialog.locator('#profile-fields-admin-label')).toHaveValue(existingLabel)

		const dialogBox = await editDialog.boundingBox()
		const saveBox = await editDialog.getByTestId('profile-fields-admin-save').boundingBox()
		expect(dialogBox).not.toBeNull()
		expect(saveBox).not.toBeNull()
		expect(dialogBox!.y + dialogBox!.height - (saveBox!.y + saveBox!.height)).toBeGreaterThan(16)

		await editDialog.getByRole('button', { name: /close/i }).click()
		await expect(editDialog).toBeHidden()

		await page.getByTestId('profile-fields-admin-new-field').click()
		const createDialog = page.getByRole('dialog', { name: 'Create field' })
		await expect(createDialog).toBeVisible()
		await createDialog.locator('#profile-fields-admin-field-key').fill(createdFieldKey)
		await createDialog.locator('#profile-fields-admin-label').fill(createdLabel)
		await createDialog.getByTestId('profile-fields-admin-save').click()

		await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field created successfully.')
		await expect(page.getByTestId(`profile-fields-admin-definition-${createdFieldKey}`)).toBeVisible()
		await expect(createDialog).toBeHidden()
	} finally {
		await deleteDefinitionByFieldKey(page.request, existingFieldKey)
		await deleteDefinitionByFieldKey(page.request, createdFieldKey)
	}
})

test('admin can reorder field definitions by dragging the list handles', async ({ page }) => {
	const suffix = Date.now()
	const firstFieldKey = `playwright_order_first_${suffix}`
	const secondFieldKey = `playwright_order_second_${suffix}`
	const firstLabel = `Playwright order first ${suffix}`
	const secondLabel = `Playwright order second ${suffix}`

	await deleteDefinitionByFieldKey(page.request, firstFieldKey)
	await deleteDefinitionByFieldKey(page.request, secondFieldKey)
	await createDefinition(page.request, {
		fieldKey: firstFieldKey,
		label: firstLabel,
		sortOrder: 0,
	})
	await createDefinition(page.request, {
		fieldKey: secondFieldKey,
		label: secondLabel,
		sortOrder: 1,
	})

	try {
		await page.goto('./settings/admin/profile_fields')
		await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

		const firstHandle = page.getByTestId(`profile-fields-admin-definition-handle-${firstFieldKey}`)
		const secondHandle = page.getByTestId(`profile-fields-admin-definition-handle-${secondFieldKey}`)
		const verticalOrder = async() => {
			const firstBox = await firstHandle.boundingBox()
			const secondBox = await secondHandle.boundingBox()
			expect(firstBox).not.toBeNull()
			expect(secondBox).not.toBeNull()
			return {
				firstY: firstBox!.y,
				secondY: secondBox!.y,
			}
		}

		let order = await verticalOrder()
		expect(order.firstY).toBeLessThan(order.secondY)

		await secondHandle.dragTo(firstHandle)

		await expect.poll(verticalOrder).toEqual(expect.objectContaining({
			firstY: expect.any(Number),
			secondY: expect.any(Number),
		}))
		order = await verticalOrder()
		expect(order.secondY).toBeLessThan(order.firstY)

		await page.reload()
		await expect(page.getByTestId('profile-fields-admin')).toBeVisible()
		order = await verticalOrder()
		expect(order.secondY).toBeLessThan(order.firstY)
	} finally {
		await deleteDefinitionByFieldKey(page.request, firstFieldKey)
		await deleteDefinitionByFieldKey(page.request, secondFieldKey)
	}
})

test('admin shows native status chip, actions menu, and drag handle in the expected order', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_layout_${suffix}`
	const label = `Playwright layout ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		active: true,
	})

	try {
		await page.goto('./settings/admin/profile_fields')
		await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

		const row = page.getByTestId(`profile-fields-admin-definition-${fieldKey}`)
		const fieldKeyText = row.getByText(fieldKey, { exact: true })
		const statusChip = row.getByText('Active', { exact: true })
		const actionsButton = row.getByRole('button', { name: `Actions for ${label}` })
		const dragHandle = page.getByTestId(`profile-fields-admin-definition-handle-${fieldKey}`)

		await expect(fieldKeyText).toBeVisible()
		await expect(statusChip).toBeVisible()
		await expect(actionsButton).toBeVisible()
		await expect(dragHandle).toBeVisible()

		const fieldKeyBox = await fieldKeyText.boundingBox()
		const statusBox = await statusChip.boundingBox()
		const actionsBox = await actionsButton.boundingBox()
		const dragHandleBox = await dragHandle.boundingBox()

		expect(fieldKeyBox).not.toBeNull()
		expect(statusBox).not.toBeNull()
		expect(actionsBox).not.toBeNull()
		expect(dragHandleBox).not.toBeNull()
		expect(fieldKeyBox!.x).toBeLessThan(statusBox!.x)
		expect(statusBox!.x).toBeLessThan(actionsBox!.x)
		expect(actionsBox!.x).toBeLessThan(dragHandleBox!.x)
	} finally {
		await deleteDefinitionByFieldKey(page.request, fieldKey)
	}
})

test('admin gets an initial select option row and can remove empty rows by keyboard', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_select_create_${suffix}`
	const label = `Playwright select create ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)

	await page.goto('./settings/admin/profile_fields')
	await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

	await page.getByTestId('profile-fields-admin-new-field').click()
	await page.locator('#profile-fields-admin-field-key').fill(fieldKey)
	await page.locator('#profile-fields-admin-label').fill(label)
	await chooseFieldType(page, 'Select')

	await expect(page.getByTestId('profile-fields-admin-option-row-0')).toBeVisible()
	await expect(optionInput(page, 0)).toHaveValue('')
	await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(0)

	await optionInput(page, 0).fill('Alpha')
	await optionInput(page, 0).press('Enter')
	await expect(page.getByTestId('profile-fields-admin-option-row-1')).toBeVisible()
	await expect(optionInput(page, 1)).toBeFocused()

	await page.locator('#profile-fields-admin-label').click()
	await expect(page.getByTestId('profile-fields-admin-option-row-1')).toHaveCount(0)

	await optionInput(page, 0).press('Enter')
	await expect(page.getByTestId('profile-fields-admin-option-row-1')).toBeVisible()
	await expect(optionInput(page, 1)).toBeFocused()

	await optionInput(page, 1).press('Backspace')
	await expect(page.getByTestId('profile-fields-admin-option-row-1')).toHaveCount(0)
	await expect(optionInput(page, 0)).toBeFocused()
	await expect(page.getByTestId('profile-fields-admin-option-handle-0')).toBeVisible()

	await page.getByTestId('profile-fields-admin-save').click()
	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field created successfully.')

	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can bulk add select options from multiple lines', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_select_bulk_${suffix}`
	const label = `Playwright select bulk ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)

	await page.goto('./settings/admin/profile_fields')
	await expect(page.getByTestId('profile-fields-admin')).toBeVisible()

	await page.getByTestId('profile-fields-admin-new-field').click()
	await page.locator('#profile-fields-admin-field-key').fill(fieldKey)
	await page.locator('#profile-fields-admin-label').fill(label)
	await chooseFieldType(page, 'Select')

	await page.getByTestId('profile-fields-admin-add-multiple-options').click()
	await page.getByTestId('profile-fields-admin-bulk-options-input').fill('Alpha\n\n Beta \nGamma')
	await page.getByTestId('profile-fields-admin-bulk-options-submit').click()

	await expect(optionInput(page, 0)).toHaveValue('Alpha')
	await expect(optionInput(page, 1)).toHaveValue('Beta')
	await expect(optionInput(page, 2)).toHaveValue('Gamma')
	await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(3)

	const continuationRow = page.getByTestId('profile-fields-admin-option-row-3')
	if (await continuationRow.count()) {
		await expect(continuationRow.locator('input')).toHaveValue('')
	}

	await page.getByTestId('profile-fields-admin-save').click()
	await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field created successfully.')

	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin reuses the empty select option row on repeated Enter', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_select_${suffix}`
	const label = `Playwright select ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		type: 'select',
		options: ['Alpha', 'Beta', 'Gamma'],
	})

	try {
		await openSelectDefinitionEditor(page, fieldKey, label)
		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Beta')
		await expect(optionInput(page, 2)).toHaveValue('Gamma')

		await optionInput(page, 2).press('Enter')
		await expect(page.getByTestId('profile-fields-admin-option-row-3')).toBeVisible()
		await expect(optionInput(page, 3)).toHaveValue('')
		await expect(optionInput(page, 3)).toBeFocused()
		await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(3)

		await optionInput(page, 3).fill('Delta')
		await expect(page.getByTestId('profile-fields-admin-option-handle-3')).toBeVisible()
		await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(4)

		await optionInput(page, 3).press('Enter')
		await expect(page.getByTestId('profile-fields-admin-option-row-4')).toBeVisible()
		await expect(optionInput(page, 4)).toHaveValue('')
		await expect(optionInput(page, 4)).toBeFocused()
		await expect(page.getByTestId('profile-fields-admin-option-handle-3')).toBeVisible()
		await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(4)

		await optionInput(page, 4).press('Enter')
		await expect(page.getByTestId('profile-fields-admin-option-row-4')).toBeVisible()
		await expect(page.getByTestId('profile-fields-admin-option-row-5')).toHaveCount(0)
		await expect(optionInput(page, 4)).toBeFocused()
		await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(4)

		await page.getByTestId('profile-fields-admin-option-handle-3').click()
		await page.getByRole('menuitem', { name: 'Move up' }).click()

		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Beta')
		await expect(optionInput(page, 2)).toHaveValue('Delta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')

		await page.getByTestId('profile-fields-admin-option-handle-2').dragTo(page.getByTestId('profile-fields-admin-option-handle-1'))

		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Delta')
		await expect(optionInput(page, 2)).toHaveValue('Beta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')
		await expect(page.getByTestId('profile-fields-admin-option-row-4')).toHaveCount(0)

		await page.getByTestId('profile-fields-admin-save').click()
		await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field updated successfully.')

		await page.reload()
		await openSelectDefinitionEditor(page, fieldKey, label)
		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Delta')
		await expect(optionInput(page, 2)).toHaveValue('Beta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')
		await expect(page.getByTestId('profile-fields-admin-option-row-4')).toHaveCount(0)
	} finally {
		await deleteDefinitionByFieldKey(page.request, fieldKey)
	}
})

test('admin can reorder select options from the handle menu and drag handle', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_reorder_${suffix}`
	const label = `Playwright reorder ${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		type: 'select',
		options: ['Alpha', 'Beta', 'Gamma', 'Delta'],
	})

	try {
		await openSelectDefinitionEditor(page, fieldKey, label)
		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Beta')
		await expect(optionInput(page, 2)).toHaveValue('Gamma')
		await expect(optionInput(page, 3)).toHaveValue('Delta')
		await expect(page.locator('[data-testid^="profile-fields-admin-option-handle-"]')).toHaveCount(4)

		await page.getByTestId('profile-fields-admin-option-handle-3').click()
		await page.getByRole('menuitem', { name: 'Move up' }).click()

		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Beta')
		await expect(optionInput(page, 2)).toHaveValue('Delta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')

		await page.getByTestId('profile-fields-admin-option-handle-2').dragTo(page.getByTestId('profile-fields-admin-option-handle-1'))

		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Delta')
		await expect(optionInput(page, 2)).toHaveValue('Beta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')

		await page.getByTestId('profile-fields-admin-save').click()
		await expect(page.getByTestId('profile-fields-admin-success')).toContainText('Field updated successfully.')

		await page.reload()
		await openSelectDefinitionEditor(page, fieldKey, label)
		await expect(optionInput(page, 0)).toHaveValue('Alpha')
		await expect(optionInput(page, 1)).toHaveValue('Delta')
		await expect(optionInput(page, 2)).toHaveValue('Beta')
		await expect(optionInput(page, 3)).toHaveValue('Gamma')
	} finally {
		await deleteDefinitionByFieldKey(page.request, fieldKey)
	}
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
		editPolicy: 'users',
		exposurePolicy: 'private',
	})

	try {
		await page.goto('./settings/user/personal-info')
		const fieldCard = page.getByTestId(`profile-fields-personal-field-${fieldKey}`)
		const fieldInput = page.getByTestId(`profile-fields-personal-input-${fieldKey}`)
		const visibilityPanel = page.getByTestId('profile-fields-personal-visibility-panel')
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
