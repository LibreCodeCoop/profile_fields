// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

/// <reference types="vite/client" />

declare module '@nextcloud/router' {
	export function generateOcsUrl(path: string, params?: object, options?: object): string
	export function generateUrl(path: string, params?: object, options?: object): string
}

interface SettingsUserListRow {
	id: string
	displayname?: string
	[key: string]: unknown
}

interface SettingsUserListApi {
	registerAction?: (
		icon: string,
		text: string,
		action: (event: Event, user: SettingsUserListRow) => void,
		enabled?: (user: SettingsUserListRow) => boolean,
	) => void
}

interface Window {
	OCA?: {
		Settings?: {
			UserList?: SettingsUserListApi
		}
	}
	__profileFieldsUserActionRegistered?: boolean
}

declare module '*.vue' {
	import type { DefineComponent } from 'vue'

	const component: DefineComponent<Record<string, never>, Record<string, never>, any>
	export default component
}
