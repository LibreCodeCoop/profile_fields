// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface CountLabelParts {
	before: string
	after: string
}

export const COUNT_PLACEHOLDER = '\uFFFC'

export const splitCountLabel = (label: string): CountLabelParts => {
	const placeholderIndex = label.indexOf(COUNT_PLACEHOLDER)
	if (placeholderIndex === -1) {
		return { before: '', after: label === '' ? '' : ` ${label}` }
	}

	return {
		before: label.slice(0, placeholderIndex),
		after: label.slice(placeholderIndex + COUNT_PLACEHOLDER.length),
	}
}
