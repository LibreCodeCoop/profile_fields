// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { rmSync } from 'node:fs'
import { mkdir, rm } from 'node:fs/promises'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { chromium, request } from '@playwright/test'

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost'
const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const screenshotDir = 'img/screenshots'
const storageStatePath = 'playwright/.tmp-storage-state.json'

const demoUser = {
	id: 'amina_okafor_demo',
	password: 'AminaDemoPass123!',
	displayName: 'Amina Okafor',
	email: 'amina.okafor@example.net',
}

const showcaseFields = [
	{
		fieldKey: 'showcase_support_region',
		label: 'Support region',
		type: 'text',
		adminOnly: false,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
		sortOrder: 10,
		adminValue: { value: 'Northern Europe', currentVisibility: 'users' },
		demoValue: { value: 'East Africa', currentVisibility: 'users' },
	},
	{
		fieldKey: 'showcase_product_specialty',
		label: 'Product specialty',
		type: 'text',
		adminOnly: false,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'public',
		sortOrder: 20,
		adminValue: { value: 'Contract automation', currentVisibility: 'public' },
		demoValue: { value: 'Identity operations', currentVisibility: 'public' },
	},
	{
		fieldKey: 'showcase_customer_segment',
		label: 'Customer segment',
		type: 'text',
		adminOnly: false,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
		sortOrder: 30,
		adminValue: { value: 'Public sector', currentVisibility: 'users' },
		demoValue: { value: 'Financial services', currentVisibility: 'users' },
	},
	{
		fieldKey: 'showcase_escalation_alias',
		label: 'Escalation alias',
		type: 'text',
		adminOnly: false,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'private',
		sortOrder: 40,
		adminValue: { value: 'north-eu-escalations', currentVisibility: 'private' },
		demoValue: { value: 'east-africa-escalations', currentVisibility: 'private' },
	},
	{
		fieldKey: 'showcase_incident_role',
		label: 'Incident response role',
		type: 'text',
		adminOnly: false,
		userEditable: true,
		userVisible: true,
		initialVisibility: 'users',
		sortOrder: 50,
		adminValue: { value: 'Communications lead', currentVisibility: 'users' },
		demoValue: { value: 'Regional coordinator', currentVisibility: 'users' },
	},
	{
		fieldKey: 'showcase_on_call_tier',
		label: 'On-call tier',
		type: 'number',
		adminOnly: true,
		userEditable: false,
		userVisible: true,
		initialVisibility: 'private',
		sortOrder: 60,
		adminValue: { value: 2, currentVisibility: 'private' },
		demoValue: { value: 1, currentVisibility: 'private' },
	},
]

const showcaseKeys = new Set(showcaseFields.map((field) => field.fieldKey))
const showcaseLabels = new Set(showcaseFields.map((field) => field.label))

async function loginApi() {
	const api = await request.newContext({ baseURL, ignoreHTTPSErrors: true })
	const tokenResponse = await api.get('./csrftoken')
	const { token: requesttoken } = await tokenResponse.json()
	const origin = tokenResponse.url().replace(/index\.php.*/, '')
	const loginResponse = await api.post('./login', {
		form: {
			user: adminUser,
			password: adminPassword,
			requesttoken,
		},
		headers: {
			Origin: origin,
		},
		maxRedirects: 0,
		failOnStatusCode: false,
	})

	if (!loginResponse.headers()['x-user-id']) {
		throw new Error(`Login failed with status ${loginResponse.status()}`)
	}

	return api
}

async function appRequest(api, method, path, body) {
	const headers = {
		'OCS-APIRequest': 'true',
		Accept: 'application/json',
	}

	if (body !== undefined) {
		headers['Content-Type'] = 'application/json'
	}

	const response = method === 'GET'
		? await api.get(path, { headers, failOnStatusCode: false })
		: method === 'POST'
			? await api.post(path, { headers, data: body, failOnStatusCode: false })
			: method === 'PUT'
				? await api.put(path, { headers, data: body, failOnStatusCode: false })
				: await api.delete(path, { headers, data: body, failOnStatusCode: false })

	const text = await response.text()
	const parsed = JSON.parse(text)

	if (!response.ok() || parsed.ocs?.meta?.status !== 'ok') {
		throw new Error(`${method} ${path} failed: ${response.status()} ${text}`)
	}

	return parsed.ocs.data
}

async function createDemoUser(api) {
	const headers = {
		'OCS-APIRequest': 'true',
		Accept: 'application/json',
	}

	await api.delete(`./ocs/v1.php/cloud/users/${demoUser.id}`, { headers, failOnStatusCode: false })
	const response = await api.post('./ocs/v1.php/cloud/users', {
		headers,
		form: {
			userid: demoUser.id,
			password: demoUser.password,
			displayName: demoUser.displayName,
			email: demoUser.email,
		},
		failOnStatusCode: false,
	})

	const text = await response.text()
	const parsed = JSON.parse(text)
	if (!response.ok() || parsed.ocs?.meta?.status !== 'ok') {
		throw new Error(`Creating demo user failed: ${response.status()} ${text}`)
	}
}

async function deleteDemoUser(api) {
	await api.delete(`./ocs/v1.php/cloud/users/${demoUser.id}`, {
		headers: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
		},
		failOnStatusCode: false,
	})
}

const hideNonShowcaseAdminDefinitions = async(page) => {
	await page.evaluate((keys) => {
		const allowedKeys = new Set(keys)
		document.querySelectorAll('[data-testid^="profile-fields-admin-definition-"]').forEach((element) => {
			const testId = element.getAttribute('data-testid') ?? ''
			const fieldKey = testId.replace('profile-fields-admin-definition-', '')
			const row = element.closest('li')
			if (row instanceof HTMLElement && !allowedKeys.has(fieldKey)) {
				row.style.display = 'none'
			}
		})
		const heroCount = document.querySelector('.profile-fields-admin__hero-meta strong')
		const heroLabel = document.querySelector('.profile-fields-admin__hero-meta span')
		if (heroCount instanceof HTMLElement) {
			heroCount.textContent = String(keys.length)
		}
		if (heroLabel instanceof HTMLElement) {
			heroLabel.textContent = 'showcase fields'
		}
	}, [...showcaseKeys])
}

const hideNonShowcasePersonalFields = async(page) => {
	await page.evaluate((keys) => {
		const allowedKeys = new Set(keys)
		document.querySelectorAll('[data-testid^="profile-fields-personal-field-"]').forEach((element) => {
			const testId = element.getAttribute('data-testid') ?? ''
			const fieldKey = testId.replace('profile-fields-personal-field-', '')
			if (element instanceof HTMLElement && !allowedKeys.has(fieldKey)) {
				element.style.display = 'none'
			}
		})
	}, [...showcaseKeys])
}

const hideNonShowcaseDialogFields = async(page) => {
	await page.evaluate(({ labels, demoUserId }) => {
		const allowedLabels = new Set(labels)
		document.querySelectorAll('.profile-fields-user-dialog__row').forEach((element) => {
			const label = element.querySelector('.profile-fields-user-dialog__field-label')?.textContent?.trim() ?? ''
			if (element instanceof HTMLElement && !allowedLabels.has(label)) {
				element.style.display = 'none'
			}
		})
		const header = document.querySelector('.profile-fields-user-dialog__header-copy')
		if (header instanceof HTMLElement) {
			header.textContent = `${labels.length} active fields for @${demoUserId}.`
		}
	}, { labels: [...showcaseLabels], demoUserId: demoUser.id })
}

const generateThumbnail = (inputName, outputName) => {
	const result = spawnSync('magick', [
		join(screenshotDir, inputName),
		'-thumbnail',
		'960x960>',
		join(screenshotDir, outputName),
	], { stdio: 'inherit' })

	if (result.status !== 0) {
		throw new Error(`Failed to create thumbnail for ${inputName}`)
	}
}

const cleanupOutput = async() => {
	rmSync(join(screenshotDir, 'admin-catalog.png'), { force: true })
	rmSync(join(screenshotDir, 'admin-catalog-thumb.png'), { force: true })
	rmSync(join(screenshotDir, 'personal-settings.png'), { force: true })
	rmSync(join(screenshotDir, 'personal-settings-thumb.png'), { force: true })
	rmSync(join(screenshotDir, 'user-management-dialog.png'), { force: true })
	rmSync(join(screenshotDir, 'user-management-dialog-thumb.png'), { force: true })
}

const run = async() => {
	const api = await loginApi()
	const createdIds = []
	let browser

	try {
		await mkdir(screenshotDir, { recursive: true })
		await cleanupOutput()

		const existingDefinitions = await appRequest(api, 'GET', './ocs/v2.php/apps/profile_fields/api/v1/definitions')
		for (const definition of existingDefinitions) {
			if (showcaseKeys.has(definition.field_key)) {
				await appRequest(api, 'DELETE', `./ocs/v2.php/apps/profile_fields/api/v1/definitions/${definition.id}`)
			}
		}

		await createDemoUser(api)

		for (const field of showcaseFields) {
			const definition = await appRequest(api, 'POST', './ocs/v2.php/apps/profile_fields/api/v1/definitions', {
				fieldKey: field.fieldKey,
				label: field.label,
				type: field.type,
				adminOnly: field.adminOnly,
				userEditable: field.userEditable,
				userVisible: field.userVisible,
				initialVisibility: field.initialVisibility,
				sortOrder: field.sortOrder,
				active: true,
			})

			createdIds.push(definition.id)
			await appRequest(api, 'PUT', `./ocs/v2.php/apps/profile_fields/api/v1/users/${encodeURIComponent(adminUser)}/values/${definition.id}`, field.adminValue)
			await appRequest(api, 'PUT', `./ocs/v2.php/apps/profile_fields/api/v1/users/${encodeURIComponent(demoUser.id)}/values/${definition.id}`, field.demoValue)
		}

		await api.storageState({ path: storageStatePath })
		browser = await chromium.launch({ headless: true })
		const context = await browser.newContext({
			baseURL,
			ignoreHTTPSErrors: true,
			storageState: storageStatePath,
			viewport: { width: 1680, height: 1500 },
			deviceScaleFactor: 2,
		})

		const adminPage = await context.newPage()
		await adminPage.goto('./settings/admin/profile_fields')
		await adminPage.getByTestId('profile-fields-admin-definition-showcase_support_region').waitFor({ state: 'visible', timeout: 60_000 })
		await hideNonShowcaseAdminDefinitions(adminPage)
		await adminPage.getByTestId('profile-fields-admin-definition-showcase_support_region').click()
		await adminPage.locator('[data-testid="profile-fields-admin"]').screenshot({ path: join(screenshotDir, 'admin-catalog.png'), type: 'png' })

		const personalPage = await context.newPage()
		await personalPage.goto('./settings/user/personal-info')
		await personalPage.getByTestId('profile-fields-personal-field-showcase_support_region').waitFor({ state: 'visible', timeout: 60_000 })
		await hideNonShowcasePersonalFields(personalPage)
		await personalPage.locator('main').screenshot({ path: join(screenshotDir, 'personal-settings.png'), type: 'png' })

		const usersPage = await context.newPage()
		await usersPage.goto('./settings/users')
		const demoRow = usersPage.getByRole('row', { name: new RegExp(demoUser.displayName) })
		await demoRow.waitFor({ state: 'visible', timeout: 60_000 })
		await demoRow.getByRole('button', { name: 'Toggle account actions menu' }).click()
		await usersPage.getByRole('menuitem', { name: 'Edit profile fields' }).click()
		const dialog = usersPage.locator('.profile-fields-user-dialog')
		await dialog.waitFor({ state: 'visible', timeout: 60_000 })
		await hideNonShowcaseDialogFields(usersPage)
		await dialog.screenshot({ path: join(screenshotDir, 'user-management-dialog.png'), type: 'png' })

		generateThumbnail('admin-catalog.png', 'admin-catalog-thumb.png')
		generateThumbnail('personal-settings.png', 'personal-settings-thumb.png')
		generateThumbnail('user-management-dialog.png', 'user-management-dialog-thumb.png')

		console.log('Generated screenshots in', screenshotDir)
	} finally {
		if (browser) {
			await browser.close()
		}

		for (const id of createdIds.reverse()) {
			try {
				await appRequest(api, 'DELETE', `./ocs/v2.php/apps/profile_fields/api/v1/definitions/${id}`)
			} catch (error) {
				console.error(`Failed to delete definition ${id}:`, error)
			}
		}

		try {
			await deleteDemoUser(api)
		} catch (error) {
			console.error('Failed to delete demo user:', error)
		}

		await api.dispose()
		await rm(storageStatePath, { force: true })
	}
}

run().catch((error) => {
	console.error(error)
	process.exitCode = 1
})
