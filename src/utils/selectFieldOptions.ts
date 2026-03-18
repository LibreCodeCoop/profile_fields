// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

export interface EditableSelectOption {
	id: string
	value: string
}

export const createEditableSelectOptions = (
	values: string[],
	createId: () => string,
): EditableSelectOption[] => values.map((value) => ({
	id: createId(),
	value,
}))

export const normalizeEditableSelectOptionValue = (value: string): string => value.trim().toLowerCase()

export const parseEditableSelectOptionValues = (value: string): string[] => value
	.split(/\r?\n/g)
	.map((entry) => entry.trim())
	.filter((entry) => entry.length > 0)

export const extractEditableSelectOptionValues = (options: EditableSelectOption[]): string[] => options.map(({ value }) => value)

export const moveEditableSelectOption = (
	options: EditableSelectOption[],
	index: number,
	direction: -1 | 1,
): EditableSelectOption[] => {
	const targetIndex = index + direction
	if (index < 0 || index >= options.length || targetIndex < 0 || targetIndex >= options.length) {
		return options
	}

	const reordered = [...options]
	const [selected] = reordered.splice(index, 1)
	reordered.splice(targetIndex, 0, selected)
	return reordered
}
