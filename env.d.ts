/// <reference types="vite/client" />

declare module '@nextcloud/router' {
	export function generateOcsUrl(path: string): string
	export function generateUrl(path: string): string
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
