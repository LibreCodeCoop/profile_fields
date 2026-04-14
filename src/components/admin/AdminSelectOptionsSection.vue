<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="profile-fields-admin-options">
		<div class="profile-fields-admin-options__heading">
			<div>
				<h4>{{ t('profile_fields', 'Options') }}</h4>
			</div>
			<div class="profile-fields-admin-options__meta">
				<strong>{{ normalizedOptionCount }}</strong>
				<span>{{ optionsCountLabel }}</span>
			</div>
		</div>

		<Draggable
			:model-value="options"
			class="profile-fields-admin-options__editor"
			data-testid="profile-fields-admin-options-editor"
			item-key="id"
			handle=".profile-fields-admin-options__handle"
			ghost-class="profile-fields-admin-options__row--ghost"
			chosen-class="profile-fields-admin-options__row--chosen"
			:animation="180"
			:disabled="isSaving"
			@update:model-value="setOptions">
			<template #item="{ element, index }">
				<div class="profile-fields-admin-options__row" :data-testid="`profile-fields-admin-option-row-${index}`">
					<div class="profile-fields-admin-options__leading">
						<NcActions
							v-if="hasOptionValue(index)"
							class="profile-fields-admin-options__handle"
							:data-testid="`profile-fields-admin-option-handle-${index}`"
							variant="tertiary-no-background"
							size="small"
							:aria-label="reorderOptionLabel(element.value)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiDragVertical" :size="18" />
							</template>
							<NcActionButton :disabled="!canMoveOptionUp(index) || isSaving" @click="moveOption(index, -1)">
								<template #icon>
									<NcIconSvgWrapper :path="mdiArrowUp" :size="18" />
								</template>
							{{ t('profile_fields', 'Move up') }}
						</NcActionButton>
						<NcActionButton :disabled="!canMoveOptionDown(index) || isSaving" @click="moveOption(index, 1)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiArrowDown" :size="18" />
							</template>
							{{ t('profile_fields', 'Move down') }}
							</NcActionButton>
						</NcActions>
						<div v-else class="profile-fields-admin-options__handle-spacer" aria-hidden="true" />
					</div>
					<NcInputField
						:model-value="element.value"
						:label="t('profile_fields', 'Option value')"
						label-outside
						:placeholder="optionPlaceholder(index + 1)"
						:error="isOptionDuplicate(index)"
						:helper-text="isOptionDuplicate(index) ? t('profile_fields', 'This option is duplicated.') : ''"
						@update:model-value="updateOption(index, $event)"
						@keydown.enter.prevent="addOptionFromEnter(index, $event)"
						@keydown.backspace="removeEmptyOptionFromKeyboard(index, $event)"
						@keydown.delete="removeEmptyOptionFromKeyboard(index, $event)"
						@blur="cleanupEmptyOptionOnBlur(element.id)"
					/>
					<div class="profile-fields-admin-options__actions">
						<NcButton
							variant="tertiary-no-background"
							:aria-label="removeOptionLabel(element.value || String(index + 1))"
							@click.prevent="removeOption(index)">
							<template #icon>
								<NcIconSvgWrapper :path="mdiClose" :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
			</template>
		</Draggable>

		<div class="profile-fields-admin-options__toolbar">
			<NcButton variant="secondary" data-testid="profile-fields-admin-add-option" @click.prevent="addOption">
			{{ t('profile_fields', 'Add single option') }}
		</NcButton>
		<NcButton variant="secondary" data-testid="profile-fields-admin-add-multiple-options" @click.prevent="openBulkOptionsDialog">
			{{ t('profile_fields', 'Add multiple options') }}
			</NcButton>
		</div>

		<NcDialog
			:open="isBulkOptionsDialogOpen"
			:name="t('profile_fields', 'Add multiple options')"
			content-classes="profile-fields-admin-options__bulk-options-dialog"
			size="normal"
			@update:open="updateBulkOptionsDialogOpen">
			<div class="profile-fields-admin-options__bulk-options-content">
				<NcTextArea
					data-testid="profile-fields-admin-bulk-options-input"
					:model-value="bulkOptionInput"
					:label="t('profile_fields', 'Options list (one per line)')"
					:placeholder="t('profile_fields', 'One option per line')"
					resize="vertical"
					rows="10"
					@update:model-value="bulkOptionInput = $event" />
				<p class="profile-fields-admin-options__bulk-options-summary">
					{{ bulkOptionsSummary }}
				</p>
			</div>

			<template #actions>
				<NcButton @click="closeBulkOptionsDialog">
					{{ t('profile_fields', 'Cancel') }}
				</NcButton>
				<NcButton
					variant="primary"
					data-testid="profile-fields-admin-bulk-options-submit"
					:disabled="bulkOptionValues.length === 0"
					@click="applyBulkOptions">
					{{ t('profile_fields', 'Add selected options') }}
				</NcButton>
			</template>
		</NcDialog>
	</section>
</template>

<script setup lang="ts">
import { mdiArrowDown, mdiArrowUp, mdiClose, mdiDragVertical } from '@mdi/js'
import { n, t } from '@nextcloud/l10n'
import { computed, ref } from 'vue'
import Draggable from 'vuedraggable'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { NcActionButton, NcActions, NcButton, NcIconSvgWrapper, NcInputField } from '@nextcloud/vue'
import { createEditableSelectOptions, extractEditableSelectOptionValues, moveEditableSelectOption, normalizeEditableSelectOptionValue, parseEditableSelectOptionValues } from '../../utils/selectFieldOptions.js'
import type { EditableSelectOption } from '../../utils/selectFieldOptions.js'

const props = defineProps<{
	modelValue: EditableSelectOption[],
	isSaving: boolean,
}>()

const emit = defineEmits<{
	(e: 'update:modelValue', value: EditableSelectOption[]): void,
}>()

const isBulkOptionsDialogOpen = ref(false)
const bulkOptionInput = ref('')
let nextOptionId = 0

const createOptionId = () => `option-local-${nextOptionId++}`

const options = computed(() => props.modelValue)
const bulkOptionValues = computed(() => parseEditableSelectOptionValues(bulkOptionInput.value))
const normalizedOptionCount = computed(() => extractEditableSelectOptionValues(options.value).filter((optionValue: string) => optionValue.trim() !== '').length)
// TRANSLATORS "Option/Options" here means selectable field values, not application settings.
const optionsCountLabel = computed(() => n('profile_fields', 'Option', 'Options', normalizedOptionCount.value, { count: normalizedOptionCount.value }))
// TRANSLATORS "{count}" is the number of parsed selectable values ready to be added.
const bulkOptionsSummary = computed(() => n('profile_fields', '{count} option ready.', '{count} options ready.', bulkOptionValues.value.length, { count: bulkOptionValues.value.length }))

const duplicateOptionIndices = computed(() => {
	const seen = new Map<string, number>()
	const duplicates = new Set<number>()
	options.value.forEach((option: EditableSelectOption, index: number) => {
		const normalized = normalizeEditableSelectOptionValue(option.value)
		if (normalized === '') {
			return
		}
		if (seen.has(normalized)) {
			duplicates.add(seen.get(normalized) as number)
			duplicates.add(index)
		} else {
			seen.set(normalized, index)
		}
	})
	return duplicates
})

const setOptions = (value: EditableSelectOption[]) => {
	emit('update:modelValue', value)
}

const hasOptionValue = (index: number) => options.value[index]?.value.trim() !== ''
const canMoveOptionUp = (index: number) => index > 0
const canMoveOptionDown = (index: number) => index < options.value.length - 1
const isOptionDuplicate = (index: number) => duplicateOptionIndices.value.has(index)
// TRANSLATORS "{optionValue}" is the visible text of one selectable option.
const reorderOptionLabel = (optionValue: string) => t('profile_fields', 'Reorder option {optionValue}', { optionValue })
// TRANSLATORS "{position}" is a 1-based option index shown as placeholder text.
const optionPlaceholder = (position: number) => t('profile_fields', 'Option {position}', { position })
// TRANSLATORS "{optionValue}" is the visible text of one selectable option.
const removeOptionLabel = (optionValue: string) => t('profile_fields', 'Remove option {optionValue}', { optionValue })

const focusOptionInput = async(index: number) => {
	requestAnimationFrame(() => {
		const input = document.querySelector<HTMLInputElement>(`[data-testid="profile-fields-admin-option-row-${index}"] input`)
		input?.focus()
	})
}

const firstEmptyOptionIndex = () => options.value.findIndex((option: EditableSelectOption) => option.value.trim() === '')

const addOption = async() => {
	const existingEmptyIndex = firstEmptyOptionIndex()
	if (existingEmptyIndex !== -1) {
		await focusOptionInput(existingEmptyIndex)
		return
	}

	setOptions([
		...options.value,
		{ id: createOptionId(), value: '' },
	])
	await focusOptionInput(options.value.length)
}

const updateOption = (index: number, value: string | number) => {
	if (options.value[index] === undefined) {
		return
	}
	const next = options.value.map((option: EditableSelectOption, optionIndex: number) => optionIndex === index
		? { ...option, value: String(value) }
		: option)
	setOptions(next)
}

const addOptionFromEnter = async(index: number, event: KeyboardEvent) => {
	const input = event.target instanceof HTMLInputElement ? event.target : null
	if (input !== null) {
		updateOption(index, input.value)
	}
	await addOption()
}

const removeOption = (index: number) => {
	setOptions(options.value.filter((_: EditableSelectOption, optionIndex: number) => optionIndex !== index))
}

const cleanupEmptyOptionOnBlur = (optionId: string) => {
	window.setTimeout(() => {
		const index = options.value.findIndex((option: EditableSelectOption) => option.id === optionId)
		if (index === -1) {
			return
		}
		if (options.value[index].value.trim() !== '') {
			return
		}
		const hasNonEmptySibling = options.value.some((option: EditableSelectOption, optionIndex: number) => optionIndex !== index && option.value.trim() !== '')
		if (!hasNonEmptySibling) {
			return
		}
		removeOption(index)
	}, 0)
}

const removeEmptyOptionFromKeyboard = async(index: number, event: KeyboardEvent) => {
	const input = event.target instanceof HTMLInputElement ? event.target : null
	if (input === null || input.value !== '' || options.value.length <= 1) {
		return
	}
	event.preventDefault()
	removeOption(index)
	await focusOptionInput(Math.max(0, index - 1))
}

const moveOption = (index: number, direction: -1 | 1) => {
	setOptions(moveEditableSelectOption(options.value, index, direction))
}

const openBulkOptionsDialog = () => {
	bulkOptionInput.value = ''
	isBulkOptionsDialogOpen.value = true
}

const closeBulkOptionsDialog = () => {
	bulkOptionInput.value = ''
	isBulkOptionsDialogOpen.value = false
}

const updateBulkOptionsDialogOpen = (open: boolean) => {
	isBulkOptionsDialogOpen.value = open
	if (!open) {
		bulkOptionInput.value = ''
	}
}

const applyBulkOptions = async() => {
	if (bulkOptionValues.value.length === 0) {
		return
	}

	setOptions([
		...options.value.filter((option: EditableSelectOption) => option.value.trim() !== ''),
		...createEditableSelectOptions(bulkOptionValues.value, createOptionId),
	])

	closeBulkOptionsDialog()
	await addOption()
}
</script>

<style scoped lang="scss">
.profile-fields-admin-options {
	display: grid;
	gap: 16px;
	padding: 18px;
	border-radius: 18px;
	background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-hover) 8%);
	border: 1px solid color-mix(in srgb, var(--color-border-default) 86%, transparent);

	&__heading {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;

		h4 {
			margin: 0;
			font-size: 16px;
		}
	}

	&__meta {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		min-width: auto;
		padding: 6px 10px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--color-main-background) 88%, var(--color-background-hover) 12%);
		border: 1px solid color-mix(in srgb, var(--color-border-default) 86%, transparent);

		strong {
			font-size: 14px;
			line-height: 1;
		}

		span {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__editor {
		display: grid;
		gap: 8px;
	}

	&__row {
		display: flex;
		align-items: flex-start;
		gap: 8px;
		padding: 10px 12px;
		border-radius: 14px;
		border: 1px solid color-mix(in srgb, var(--color-border-default) 84%, transparent);
		background: color-mix(in srgb, var(--color-main-background) 94%, var(--color-background-hover) 6%);

		:deep(.input-field) {
			flex: 1;
			margin-bottom: 0;
		}
	}

	&__leading,
	&__actions {
		display: flex;
		align-items: flex-start;
		flex: 0 0 auto;
	}

	&__leading {
		min-width: 42px;
	}

	&__handle,
	&__handle-spacer {
		width: 42px;
	}

	&__handle {
		:deep(.action-item),
		:deep(.action-item__wrapper) {
			width: 100%;
		}

		:deep(.button-vue) {
			width: 100%;
			cursor: grab;
		}
	}

	&__handle-spacer {
		height: 34px;
	}

	&__row--ghost {
		opacity: 0.45;
	}

	&__row--chosen {
		box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 22%, transparent);
	}

	&__toolbar {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
	}

	&__bulk-options-content {
		display: grid;
		gap: 12px;
	}

	&__bulk-options-summary {
		margin: 0;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
	}
}

@media (max-width: 1360px) {
	.profile-fields-admin-options {
		&__heading {
			flex-direction: column;
		}

		&__meta {
			justify-items: start;
		}
	}
}
</style>
