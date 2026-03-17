// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	'settings-admin': 'src/settings-admin.ts',
	'settings-personal': 'src/settings-personal.ts',
	workflow: 'src/workflow.ts',
	'user-management-action': 'src/user-management-action.ts',
})
