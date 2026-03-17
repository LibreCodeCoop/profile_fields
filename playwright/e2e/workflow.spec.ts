// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { expect, test, type Locator, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { createDefinition, deleteDefinitionByFieldKey } from '../support/profile-fields'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const escapeRegex = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const selectNcOption = async(page: Page, combobox: Locator, optionName: string) => {
	await combobox.click()
	await page.locator('[role="option"]').filter({
		hasText: new RegExp(`^\\s*${escapeRegex(optionName)}\\s*$`),
	}).first().click()
}

const configureDraftRule = async(page: Page, actionName: string, label: string, fieldValue: string, operationValue?: string) => {
	const initialRuleCount = await page.locator('.section.rule').count()
	const addFlowCard = page.locator('.actions__item.colored').filter({
		has: page.getByRole('heading', { name: actionName, exact: true }),
	})
	await expect(addFlowCard).toBeVisible()
	await addFlowCard.getByRole('button', { name: 'Add new flow' }).click()

	const configuredRule = page.locator('.section.rule').filter({
		has: page.getByRole('button', { name: 'Cancel', exact: true }),
	}).last()
	await expect(configuredRule).toBeVisible()
	const configuredRuleIndex = await configuredRule.evaluate((element) => {
		return Array.from(document.querySelectorAll('.section.rule')).indexOf(element)
	})

	await expect(configuredRule.getByText('Profile field value updated', { exact: true })).toBeVisible()
	await selectNcOption(page, configuredRule.getByRole('combobox', { name: 'Select a filter' }), 'Profile field value')
	await selectNcOption(page, configuredRule.locator('.comparator [role="combobox"]'), 'is')

	const checkEditor = configuredRule.locator('oca-profile-fields-check-user-profile-field')
	await expect(checkEditor).toBeVisible()
	await checkEditor.locator('select').selectOption({ label })
	await checkEditor.locator('input').fill(fieldValue)

	if (operationValue !== undefined) {
		const operationInput = configuredRule.locator('input[type="url"]')
		await expect(operationInput).toBeVisible()
		await operationInput.fill(operationValue)
	}

	await expect(configuredRule.getByRole('button', { name: 'Save' })).toBeVisible()
	await configuredRule.getByRole('button', { name: 'Save' }).click()

	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount + 1)
	const savedRule = page.locator('.section.rule').nth(configuredRuleIndex)
	await expect(savedRule.getByText('Profile field value updated', { exact: true })).toBeVisible()
	await expect(savedRule.getByRole('button', { name: 'Active' })).toBeVisible()

	return { savedRule, initialRuleCount }
}

test.beforeEach(async ({ page }) => {
	await login(page.request, adminUser, adminPassword)
})

test('admin can create a profile field workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_workflow_${suffix}`
	const label = `Playwright workflow ${suffix}`
	const fieldValue = `engineering-${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
	})

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Log profile field change', label, fieldValue)

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can create a notify affected user workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_notify_workflow_${suffix}`
	const label = `Playwright notify workflow ${suffix}`
	const fieldValue = `engineering-notify-${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
	})

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Notify affected user', label, fieldValue)

	await expect(savedRule.getByText('Notify affected user', { exact: true })).toBeVisible()

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can create a send webhook workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_webhook_workflow_${suffix}`
	const label = `Playwright webhook workflow ${suffix}`
	const fieldValue = `engineering-webhook-${suffix}`
	const webhookUrl = `https://example.test/hooks/profile-fields/${suffix}`

	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
	})

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Send webhook', label, fieldValue, webhookUrl)

	await expect(savedRule.getByText('Send webhook', { exact: true })).toBeVisible()
	await expect(savedRule.locator('input[type="url"]')).toHaveValue(webhookUrl)

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})
