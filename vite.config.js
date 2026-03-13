import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	'settings-admin': 'src/settings-admin.ts',
	'settings-personal': 'src/settings-personal.ts',
	'user-management-action': 'src/user-management-action.ts',
})
