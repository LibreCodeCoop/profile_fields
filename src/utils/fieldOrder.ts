// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

import type { FieldDefinition } from '../types'

export interface FieldOrderUpdate {
	id: number
	sortOrder: number
}

const byDisplayOrder = (left: FieldDefinition, right: FieldDefinition): number => left.sort_order - right.sort_order || left.id - right.id

export const buildFieldOrderUpdates = (
	definitions: FieldDefinition[],
	selectedId: number,
	direction: -1 | 1,
): FieldOrderUpdate[] => {
	const ordered = [...definitions].sort(byDisplayOrder)
	const currentIndex = ordered.findIndex((definition) => definition.id === selectedId)
	if (currentIndex === -1) {
		return []
	}

	const targetIndex = currentIndex + direction
	if (targetIndex < 0 || targetIndex >= ordered.length) {
		return []
	}

	const reordered = [...ordered]
	const [selected] = reordered.splice(currentIndex, 1)
	reordered.splice(targetIndex, 0, selected)

	const baseSortOrder = ordered.reduce((lowest, definition) => Math.min(lowest, definition.sort_order), ordered[0]?.sort_order ?? 0)

	return reordered
		.map((definition, index) => ({
			id: definition.id,
			sortOrder: baseSortOrder + index,
		}))
		.filter((candidate) => ordered.find((definition) => definition.id === candidate.id)?.sort_order !== candidate.sortOrder)
}
