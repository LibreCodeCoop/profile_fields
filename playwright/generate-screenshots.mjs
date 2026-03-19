// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { rmSync } from 'node:fs'
import { mkdir, readFile, rm } from 'node:fs/promises'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'
import { chromium, request } from '@playwright/test'
import { pedroPotiPersona } from '../src/utils/pedroPotiPersona.js'

const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://localhost'
const adminUser = process.env.NEXTCLOUD_ADMIN_USER ?? 'admin'
const adminPassword = process.env.NEXTCLOUD_ADMIN_PASSWORD ?? 'admin'
const screenshotDir = 'img/screenshots'
const adminStorageStatePath = 'playwright/.tmp-admin-storage-state.json'
const demoStorageStatePath = 'playwright/.tmp-demo-storage-state.json'
const demoAvatarPath = 'playwright/fixtures/pedro-poti-avatar.png'

const legacyDemoUserIds = ['amina_okafor_demo', 'araci_potira_demo']

const demoUser = pedroPotiPersona.user
const showcaseFields = pedroPotiPersona.showcaseFields

const showcaseKeys = new Set(showcaseFields.map((field) => field.fieldKey))
const showcaseLabels = new Set(showcaseFields.map((field) => field.label))
const transientFieldKeyPrefixes = ['showcase_', 'playwright_']

const isTransientScreenshotDefinition = (definition) => transientFieldKeyPrefixes
	.some((prefix) => definition.field_key.startsWith(prefix))


async function loginApi(user = adminUser, password = adminPassword) {
	const api = await request.newContext({ baseURL, ignoreHTTPSErrors: true })
	const tokenResponse = await api.get('./csrftoken')
	const { token: requesttoken } = await tokenResponse.json()
	const origin = tokenResponse.url().replace(/index\.php.*/, '')
	const loginResponse = await api.post('./login', {
		form: {
			user,
			password,
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

async function getRequestToken(api) {
	const response = await api.get('./csrftoken')
	const parsed = await response.json()
	return parsed.token
}

async function uploadCurrentUserAvatar(api, imagePath) {
	const imageBuffer = await readFile(imagePath)
	const requesttoken = await getRequestToken(api)
	const response = await api.post('./avatar/', {
		headers: {
			requesttoken,
		},
		multipart: {
			'files[]': {
				name: imagePath.split('/').pop() ?? 'avatar.png',
				mimeType: 'image/png',
				buffer: imageBuffer,
			},
		},
		failOnStatusCode: false,
	})

	const body = await response.text()
	let parsed

	try {
		parsed = JSON.parse(body)
	} catch {
		throw new Error(`Avatar upload failed: ${response.status()} ${body}`)
	}

	if (!response.ok() || parsed.status !== 'success') {
		throw new Error(`Avatar upload failed: ${response.status()} ${body}`)
	}
}

const waitForAvatarImage = async(page) => {
	await page.waitForFunction(() => {
		const avatarImages = [...document.querySelectorAll('img')]
			.filter((img) => img.src.includes('/avatar/'))
		return avatarImages.some((img) => img.complete && img.naturalWidth > 0)
	}, { timeout: 60_000 })
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

	for (const legacyUserId of legacyDemoUserIds) {
		await api.delete(`./ocs/v1.php/cloud/users/${legacyUserId}`, { headers, failOnStatusCode: false })
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
	for (const legacyUserId of legacyDemoUserIds) {
		await api.delete(`./ocs/v1.php/cloud/users/${legacyUserId}`, {
			headers: {
				'OCS-APIRequest': 'true',
				Accept: 'application/json',
			},
			failOnStatusCode: false,
		})
	}

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
			if (testId.startsWith('profile-fields-admin-definition-handle-')) {
				return
			}
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

const fillAccountField = async(page, label, value) => {
	const textboxes = page.getByRole('textbox', { name: label, exact: true })
	const input = await textboxes.count() > 0
		? textboxes.first()
		: page.locator(`input[aria-label="${label}"], textarea[aria-label="${label}"]`).first()
	await input.waitFor({ state: 'visible', timeout: 60_000 })
	await input.fill(value)
	await input.blur()
	await page.waitForTimeout(250)
}

const seedPedroPotiAccountProfile = async(page) => {
	for (const field of pedroPotiPersona.accountFields) {
		await fillAccountField(page, field.label, field.value)
	}

	await page.waitForTimeout(800)
}

const prepareWorkflowScreenshot = async(page) => {
	await page.goto('./settings/admin/workflow')
	await page.getByRole('heading', { name: 'Available flows' }).waitFor({ state: 'visible', timeout: 60_000 })

	const showMoreButton = page.getByRole('button', { name: 'Show more', exact: true })
	if (await showMoreButton.count() > 0) {
		await showMoreButton.click()
		await page.getByRole('button', { name: 'Show less', exact: true }).waitFor({ state: 'visible', timeout: 60_000 })
	}

	await page.getByRole('heading', { name: 'Create Talk conversation', exact: true }).first().waitFor({ state: 'visible', timeout: 60_000 })
	await page.waitForTimeout(800)

	await page.evaluate(() => {
		document.querySelector('header')?.setAttribute('style', 'display:none')
		document.querySelector('#app-navigation')?.setAttribute('style', 'display:none')
		document.querySelector('.settings-menu')?.setAttribute('style', 'display:none')
	})

	return page.locator('#workflowengine .settings-section').first()
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
	rmSync(join(screenshotDir, 'workflow-notify-admins.png'), { force: true })
	rmSync(join(screenshotDir, 'workflow-notify-admins-thumb.png'), { force: true })
}

const run = async() => {
	const api = await loginApi()
	let demoApi
	const createdIds = []
	let browser

	try {
		await mkdir(screenshotDir, { recursive: true })
		await cleanupOutput()

		const existingDefinitions = await appRequest(api, 'GET', './ocs/v2.php/apps/profile_fields/api/v1/definitions')
		for (const definition of existingDefinitions) {
			if (isTransientScreenshotDefinition(definition)) {
				await appRequest(api, 'DELETE', `./ocs/v2.php/apps/profile_fields/api/v1/definitions/${definition.id}`)
			}
		}

		await createDemoUser(api)
		demoApi = await loginApi(demoUser.id, demoUser.password)
		await uploadCurrentUserAvatar(demoApi, demoAvatarPath)
		await demoApi.dispose()
		demoApi = await loginApi(demoUser.id, demoUser.password)

		for (const field of showcaseFields) {
			const definition = await appRequest(api, 'POST', './ocs/v2.php/apps/profile_fields/api/v1/definitions', {
				fieldKey: field.fieldKey,
				label: field.label,
				type: field.type,
				...(field.type === 'select' ? { options: field.options ?? [] } : {}),
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

		await api.storageState({ path: adminStorageStatePath })
		await demoApi.storageState({ path: demoStorageStatePath })
		browser = await chromium.launch({ headless: true })
		const adminContext = await browser.newContext({
			baseURL,
			ignoreHTTPSErrors: true,
			storageState: adminStorageStatePath,
			viewport: { width: 1680, height: 1500 },
			deviceScaleFactor: 2,
		})
		const demoContext = await browser.newContext({
			baseURL,
			ignoreHTTPSErrors: true,
			storageState: demoStorageStatePath,
			viewport: { width: 1680, height: 1500 },
			deviceScaleFactor: 2,
		})

		const adminPage = await adminContext.newPage()
		await adminPage.goto('./settings/admin/profile_fields')
		await adminPage.getByTestId('profile-fields-admin-definition-showcase_support_region').waitFor({ state: 'visible', timeout: 60_000 })
		await hideNonShowcaseAdminDefinitions(adminPage)
		await adminPage.getByTestId('profile-fields-admin-definition-showcase_council_channel').click()
		await adminPage.locator('[data-testid="profile-fields-admin"]').screenshot({ path: join(screenshotDir, 'admin-catalog.png'), type: 'png' })

		const personalPage = await demoContext.newPage()
		await personalPage.goto('./settings/user/personal-info')
		await waitForAvatarImage(personalPage)
		await personalPage.getByTestId('profile-fields-personal-field-showcase_support_region').waitFor({ state: 'visible', timeout: 60_000 })
		await seedPedroPotiAccountProfile(personalPage)
		await hideNonShowcasePersonalFields(personalPage)
		await personalPage.locator('main').screenshot({ path: join(screenshotDir, 'personal-settings.png'), type: 'png' })

		const usersPage = await adminContext.newPage()
		await usersPage.goto('./settings/users')
		const demoRow = usersPage.getByRole('row', { name: new RegExp(demoUser.displayName) })
		await demoRow.waitFor({ state: 'visible', timeout: 60_000 })
		await demoRow.getByRole('button', { name: 'Toggle account actions menu' }).click()
		await usersPage.getByRole('menuitem', { name: 'Edit profile fields' }).click()
		const dialog = usersPage.locator('.profile-fields-user-dialog')
		await dialog.waitFor({ state: 'visible', timeout: 60_000 })
		await usersPage.locator('.profile-fields-user-dialog__loading').waitFor({ state: 'hidden', timeout: 60_000 }).catch(() => {})
		await dialog.locator('.profile-fields-user-dialog__row').first().waitFor({ state: 'visible', timeout: 60_000 })
		await hideNonShowcaseDialogFields(usersPage)
		await dialog.screenshot({ path: join(screenshotDir, 'user-management-dialog.png'), type: 'png' })

		const workflowPage = await adminContext.newPage()
		const workflowSection = await prepareWorkflowScreenshot(workflowPage)
		await workflowSection.screenshot({ path: join(screenshotDir, 'workflow-notify-admins.png'), type: 'png' })

		generateThumbnail('admin-catalog.png', 'admin-catalog-thumb.png')
		generateThumbnail('personal-settings.png', 'personal-settings-thumb.png')
		generateThumbnail('user-management-dialog.png', 'user-management-dialog-thumb.png')
		generateThumbnail('workflow-notify-admins.png', 'workflow-notify-admins-thumb.png')

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

		if (demoApi) {
			await demoApi.dispose()
		}

		await api.dispose()
		await rm(adminStorageStatePath, { force: true })
		await rm(demoStorageStatePath, { force: true })
	}
}

run().catch((error) => {
	console.error(error)
	process.exitCode = 1
})
