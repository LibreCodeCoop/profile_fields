// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { expect, test, type Locator, type Page } from '@playwright/test'
import { login } from '../support/nc-login'
import { createDefinition, deleteDefinitionByFieldKey } from '../support/profile-fields'

test.describe.configure({ mode: 'serial' })

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

const escapeRegex = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const selectNcOption = async(page: Page, combobox: Locator, optionName: string) => {
	await combobox.click()
	await page.locator('[role="option"]').filter({
		hasText: new RegExp(`^\\s*${escapeRegex(optionName)}\\s*$`),
	}).first().click()
}

const ensureFlowCardIsVisible = async(page: Page, addFlowCard: Locator) => {
	if (await addFlowCard.count() > 0) {
		return
	}

	const showMoreButton = page.getByRole('button', { name: 'Show more', exact: true })
	if (await showMoreButton.count() > 0) {
		await showMoreButton.click()
	}

	await expect(addFlowCard).toBeVisible()
}

const configureDraftRule = async(page: Page, actionName: string, label: string, fieldValue: string, configureOperation?: (configuredRule: Locator) => Promise<void>, operationValue?: string) => {
	const initialRuleCount = await page.locator('.section.rule').count()
	const addFlowCard = page.locator('.actions__item.colored').filter({
		has: page.getByRole('heading', { name: actionName, exact: true }),
	})
	await ensureFlowCardIsVisible(page, addFlowCard)
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

	if (configureOperation !== undefined) {
		await configureOperation(configuredRule)
	}

	await expect(configuredRule.getByRole('button', { name: 'Save' })).toBeVisible()
	await configuredRule.getByRole('button', { name: 'Save' }).click()

	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount + 1)
	const savedRule = page.locator('.section.rule').nth(configuredRuleIndex)
	await expect(savedRule.getByText('Profile field value updated', { exact: true })).toBeVisible()
	await expect(savedRule.getByRole('button', { name: 'Active' })).toBeVisible()

	return { savedRule, initialRuleCount }
}

const createWorkflowFieldDefinition = async(page: Page, fieldKey: string, label: string) => {
	await deleteDefinitionByFieldKey(page.request, fieldKey)
	await createDefinition(page.request, {
		fieldKey,
		label,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
	})
}

test.beforeEach(async ({ page }) => {
	await login(page.request, adminUser, adminPassword)
})

test('admin can create a profile field workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_workflow_${suffix}`
	const label = `Playwright workflow ${suffix}`
	const fieldValue = `engineering-${suffix}`

	await createWorkflowFieldDefinition(page, fieldKey, label)

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Log profile field change', label, fieldValue)

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

	await createWorkflowFieldDefinition(page, fieldKey, label)

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Send webhook', label, fieldValue, async (configuredRule) => {
		await configuredRule.locator(`input[placeholder="Optional shared secret for HMAC signatures"]`).fill(`secret-${suffix}`)
		await configuredRule.locator(`input[placeholder="Timeout in seconds"]`).fill('10')
	}, webhookUrl)

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can create an email affected user workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_email_workflow_${suffix}`
	const label = `Playwright email workflow ${suffix}`
	const fieldValue = `engineering-email-${suffix}`

	await createWorkflowFieldDefinition(page, fieldKey, label)

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Email affected user', label, fieldValue, async (configuredRule) => {
		await configuredRule.locator(`input[placeholder="Optional email subject template"]`).fill('Update: {{fieldLabel}}')
		await configuredRule.locator('textarea').fill('Field {{fieldLabel}} changed from {{previousValue}} to {{currentValue}}.')
	})

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can create a notify admins or groups workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_admin_notify_workflow_${suffix}`
	const label = `Playwright admin notify workflow ${suffix}`
	const fieldValue = `engineering-admin-notify-${suffix}`

	await createWorkflowFieldDefinition(page, fieldKey, label)

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Notify admins or groups', label, fieldValue, async (configuredRule) => {
		const targetsEditor = configuredRule.locator('oca-profile-fields-targets-operation')
		await expect(targetsEditor).toBeVisible()

		await targetsEditor.getByRole('combobox').click()
		await targetsEditor.getByRole('searchbox').fill('admin')
		await expect(page.locator('.vs__dropdown-menu')).toBeVisible()
		await page.locator('.vs__dropdown-option').filter({ hasText: /Administrators/ }).first().click()
		await expect(targetsEditor).toContainText('Administrators')
	})

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})

test('admin can create a create Talk conversation workflow rule', async ({ page }) => {
	const suffix = Date.now()
	const fieldKey = `playwright_talk_workflow_${suffix}`
	const label = `Playwright talk workflow ${suffix}`
	const fieldValue = `engineering-talk-${suffix}`

	await createWorkflowFieldDefinition(page, fieldKey, label)

	await page.goto('./settings/admin/workflow')
	await expect(page.getByRole('heading', { name: 'Available flows' })).toBeVisible()
	const { savedRule, initialRuleCount } = await configureDraftRule(page, 'Create Talk conversation', label, fieldValue)

	await savedRule.getByRole('button', { name: 'Delete' }).click()
	await expect(page.locator('.section.rule')).toHaveCount(initialRuleCount)
	await deleteDefinitionByFieldKey(page.request, fieldKey)
})
