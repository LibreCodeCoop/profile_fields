import { expect, test } from '@playwright/test'
import { login } from '../support/nc-login'
import { createDefinition, deleteDefinitionByFieldKey } from '../support/profile-fields'

const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'

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
		await expect(page.getByTestId('profile-fields-personal')).toBeVisible()
		await expect(page.getByTestId(`profile-fields-personal-field-${fieldKey}`)).toBeVisible()

		const input = page.locator(`#profile-fields-personal-value-${definition.id}`)
		await input.fill(value)

		await expect(page.getByTestId(`profile-fields-personal-success-${fieldKey}`)).toContainText('Saved automatically.')

		await page.reload()
		await expect(page.getByTestId(`profile-fields-personal-field-${fieldKey}`)).toBeVisible()
		await expect(page.locator(`#profile-fields-personal-value-${definition.id}`)).toHaveValue(value)
	} finally {
		await deleteDefinitionByFieldKey(page.request, fieldKey)
	}
})
