// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { FieldVisibility } from '../types'

export type VisibilityOption = {
	value: FieldVisibility
	label: string
}

export const visibilityOptions: VisibilityOption[] = [
	{ value: 'public', label: 'Show to everyone' },
	{ value: 'users', label: 'Show to logged in accounts only' },
	{ value: 'private', label: 'Hide' },
]
