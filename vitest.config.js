// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { defineConfig } from 'vitest/config'

export default defineConfig({
	test: {
		include: ['src/tests/**/*.{test,spec}.ts'],
		environment: 'node',
	},
})
