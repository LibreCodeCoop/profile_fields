<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors

SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open="open"
		size="large"
		:name="t('profile_fields', 'Edit additional profile fields')"
		content-classes="profile-fields-user-dialog__content"
		@update:open="updateOpen">
		<div class="profile-fields-user-dialog">
			<header class="profile-fields-user-dialog__header">
				<NcAvatar :key="userUid" :user="userUid" :size="40" disable-menu hide-status />

				<div class="profile-fields-user-dialog__header-copy-block">
					<h2>{{ headerUserName }}</h2>
					<p class="profile-fields-user-dialog__header-copy">{{ headerDescription }}</p>
				</div>
			</header>

			<NcNoteCard v-if="errorMessage" type="error">
				{{ errorMessage }}
			</NcNoteCard>

			<NcNoteCard v-if="successMessage" type="success">
				{{ successMessage }}
			</NcNoteCard>

			<div v-if="isLoading" class="profile-fields-user-dialog__loading">
				<NcLoadingIcon :size="32" />
				<span>{{ loadingMessage }}</span>
			</div>

			<NcEmptyContent
				v-else-if="editableFields.length === 0"
				:name="t('profile_fields', 'No editable fields')"
				:description="t('profile_fields', 'Create and enable fields in the admin catalog. They will appear here automatically.')" />

			<div v-else class="profile-fields-user-dialog__list">
				<article v-for="field in editableFields" :key="field.definition.id" class="profile-fields-user-dialog__row" :class="{ 'profile-fields-user-dialog__row--error': fieldHasError(field) }">
					<div class="profile-fields-user-dialog__row-header">
						<label class="profile-fields-user-dialog__field-label" :for="`profile-fields-user-dialog-value-${field.definition.id}`">{{ field.definition.label }}</label>
					</div>

					<div class="profile-fields-user-dialog__row-body">
						<NcSelect
							v-if="field.definition.type === 'select'"
							class="profile-fields-user-dialog__input"
							:input-id="`profile-fields-user-dialog-value-${field.definition.id}`"
							:model-value="selectOptionFor(field.definition.id)"
							:aria-label="field.definition.label"
							:clearable="true"
							:searchable="false"
							:options="selectOptionsFor(field.definition)"
							label="label"
							:placeholder="placeholderForField(field.definition.type)"
							@update:model-value="updateSelectValue(field.definition.id, $event)"
						/>
						<NcSelect
							v-else-if="field.definition.type === 'multiselect'"
							class="profile-fields-user-dialog__input"
							:input-id="`profile-fields-user-dialog-value-${field.definition.id}`"
							:model-value="multiselectOptionValuesFor(field.definition.id)"
							:aria-label="field.definition.label"
							:clearable="true"
							:searchable="false"
							:multiple="true"
							:options="selectOptionsFor(field.definition)"
							label="label"
							:placeholder="placeholderForField(field.definition.type)"
							@update:model-value="updateMultiSelectValue(field.definition.id, $event)"
						/>
						<NcInputField
							v-else
							class="profile-fields-user-dialog__input"
							:id="`profile-fields-user-dialog-value-${field.definition.id}`"
							v-model="userDraftValues[field.definition.id]"
							:aria-label="field.definition.label"
							:error="fieldHasError(field)"
							:helper-text="helperMessageForField(field)"
							label-outside
							:type="componentInputTypeForField(field.definition.type)"
							:inputmode="inputModeForField(field.definition.type)"
							:placeholder="placeholderForField(field.definition.type)"
							@update:model-value="clearFieldError(field.definition.id)"
						/>

						<div class="profile-fields-user-dialog__visibility-control" :class="{ 'profile-fields-user-dialog__visibility-control--error': fieldHasError(field) }">
							<label class="profile-fields-user-dialog__control-label" :for="`profile-fields-user-dialog-visibility-${field.definition.id}`">{{ visibilityFieldLabel }}</label>
							<NcSelect
								:input-id="`profile-fields-user-dialog-visibility-${field.definition.id}`"
								:model-value="visibilityOptionFor(field.definition.id)"
								label="label"
								:clearable="false"
								:searchable="false"
								:options="visibilityOptions"
								@update:model-value="updateVisibility(field.definition.id, $event)"
							/>
						</div>
					</div>

				</article>
			</div>
		</div>

		<template #actions>
			<NcButton @click="closeDialog">
				{{ t('profile_fields', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!hasPendingChanges || hasInvalidFields || isSavingAny || isLoading" @click="saveAllFields">
				{{ isSavingAny ? t('profile_fields', 'Saving changes...') : t('profile_fields', 'Save changes') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script lang="ts">
import { computed, defineComponent, reactive, ref, watch } from 'vue'
import { n, t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import { NcButton, NcDialog, NcEmptyContent, NcInputField, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { listAdminUserValues, listDefinitions, upsertAdminUserValue } from '../api'
import { definitionDefaultVisibility } from '../types'
import type { AdminEditableField, FieldDefinition, FieldType, FieldValueRecord, FieldVisibility } from '../types'
import { buildAdminEditableFields } from '../utils/adminFieldValues.js'
import { visibilityOptions } from '../utils/visibilityOptions.js'

export default defineComponent({
	name: 'AdminUserFieldsDialog',
	components: {
		NcAvatar,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcInputField,
		NcLoadingIcon,
		NcNoteCard,
		NcSelect,
	},
	props: {
		open: {
			type: Boolean,
			required: true,
		},
		userUid: {
			type: String,
			required: true,
		},
		userDisplayName: {
			type: String,
			required: true,
		},
	},
	emits: {
		'update:open': (value: boolean) => typeof value === 'boolean',
	},
	setup(props: { open: boolean, userUid: string, userDisplayName: string }, { emit }: { emit: (event: 'update:open', value: boolean) => void }) {
		const definitions = ref<FieldDefinition[]>([])
		const userValues = ref<FieldValueRecord[]>([])
		const isLoading = ref(false)
		const errorMessage = ref('')
		const successMessage = ref('')
		const savingIds = ref<number[]>([])

		const userValueErrors = reactive<Record<number, string>>({})
		const userDraftValues = reactive<Record<number, string | string[]>>({})
		const userDraftVisibilities = reactive<Record<number, FieldVisibility>>({})

		const headerUserName = computed(() => props.userDisplayName.trim() !== '' ? props.userDisplayName : props.userUid)
		const visibilityFieldLabel = t('profile_fields', 'Who can view this field value')
		const loadingMessage = computed(() => t('profile_fields', 'Loading profile fields for {userUid}...', { userUid: props.userUid }))
		const editableFields = computed<AdminEditableField[]>(() => buildAdminEditableFields(definitions.value, userValues.value))
		const isSavingAny = computed(() => savingIds.value.length > 0)
		const headerDescription = computed(() => {
			if (props.userUid === '') {
				return t('profile_fields', 'Update additional profile fields for the selected account.')
			}

			const count = editableFields.value.length
			// TRANSLATORS "{count}" is the number of editable fields and "{userUid}" is the account ID (without @).
			return n('profile_fields', '{count} editable field for @{userUid}.', '{count} editable fields for @{userUid}.', count, {
				count,
				userUid: props.userUid,
			})
		})

		const clearRecord = (record: Record<string | number, unknown>) => {
			Object.keys(record).forEach((key) => {
				delete record[key]
			})
		}

		const descriptionForType = (type: FieldType): string => ({
			text: t('profile_fields', 'Free text stored as a scalar value.'),
			number: t('profile_fields', 'Only numeric values are accepted.'),
			date: t('profile_fields', 'Use a valid date in YYYY-MM-DD format.'),
			select: t('profile_fields', 'Choose one of the predefined options.'),
			multiselect: t('profile_fields', 'Choose one or more predefined options.'),
		} as Record<FieldType, string>)[type]

		const placeholderForField = (type: FieldType): string => ({
			text: t('profile_fields', 'Enter a value'),
			number: t('profile_fields', 'Enter a number'),
			date: t('profile_fields', 'Select a date'),
			select: t('profile_fields', 'Select an option'),
			multiselect: t('profile_fields', 'Select one or more options'),
		} as Record<FieldType, string>)[type]

		const plainNumberPattern = /^-?\d+(\.\d+)?$/
		const isoDatePattern = /^\d{4}-\d{2}-\d{2}$/

		const inputModeForField = (type: FieldType): string => ({
			text: 'text',
			number: 'decimal',
			date: 'numeric',
			select: 'text',
			multiselect: 'text',
		} as Record<FieldType, string>)[type]

		const componentInputTypeForField = (type: FieldType): string => ({
			text: 'text',
			number: 'text',
			date: 'date',
			select: 'text',
			multiselect: 'text',
		} as Record<FieldType, string>)[type]

		const selectOptionsFor = (definition: FieldDefinition) =>
			(definition.options ?? []).map((opt: string) => ({ value: opt, label: opt }))

		const selectOptionFor = (fieldId: number) => {
			const current = userDraftValues[fieldId]
			const value = typeof current === 'string' ? current.trim() : ''
			return value ? { value, label: value } : null
		}

		const updateSelectValue = (fieldId: number, option: { value: string, label: string } | null) => {
			userDraftValues[fieldId] = option?.value ?? ''
			clearFieldError(fieldId)
		}

		const multiselectOptionValuesFor = (fieldId: number) => {
			const current = userDraftValues[fieldId]
			if (!Array.isArray(current)) {
				return []
			}

			return current.map((value: string) => ({ value, label: value }))
		}

		const updateMultiSelectValue = (fieldId: number, options: Array<{ value: string, label: string }> | null) => {
			userDraftValues[fieldId] = options?.map((option) => option.value) ?? []
			clearFieldError(fieldId)
		}

		const rawDraftValueFor = (fieldId: number) => {
			const current = userDraftValues[fieldId]
			return typeof current === 'string' ? current.trim() : ''
		}

		const validateField = (field: AdminEditableField): string | null => {
			const rawValue = rawDraftValueFor(field.definition.id)

			if (rawValue === '') {
				return null
			}

			if (field.definition.type === 'number' && !plainNumberPattern.test(rawValue)) {
				// TRANSLATORS "{fieldLabel}" is a profile field label.
				return t('profile_fields', '{fieldLabel} must be a plain numeric value.', { fieldLabel: field.definition.label })
			}

			if (field.definition.type === 'date') {
				const parsed = isoDatePattern.test(rawValue) ? new Date(`${rawValue}T00:00:00Z`) : null
				const normalized = parsed instanceof Date && !Number.isNaN(parsed.getTime()) ? parsed.toISOString().slice(0, 10) : ''
				if (normalized !== rawValue) {
					return t('profile_fields', '{fieldLabel} must be a valid date in YYYY-MM-DD format.', { fieldLabel: field.definition.label })
				}
			}

			if (field.definition.type === 'select') {
				const options = field.definition.options ?? []
				if (!options.includes(rawValue)) {
					// TRANSLATORS "{fieldLabel}" is a profile field label.
					return t('profile_fields', '{fieldLabel} must be one of the allowed options.', { fieldLabel: field.definition.label })
				}
			}

			if (field.definition.type === 'multiselect') {
				const current = userDraftValues[field.definition.id]
				if (!Array.isArray(current)) {
					return t('profile_fields', '{fieldLabel} must be one of the allowed options.', { fieldLabel: field.definition.label })
				}

				const options = field.definition.options ?? []
				if (current.some((value: string) => !options.includes(value))) {
					return t('profile_fields', '{fieldLabel} must be one of the allowed options.', { fieldLabel: field.definition.label })
				}
			}

			return null
		}

		const validationErrorForField = (field: AdminEditableField) => validateField(field)
		const displayedFieldError = (field: AdminEditableField) => userValueErrors[field.definition.id] ?? validationErrorForField(field)
		const fieldHasError = (field: AdminEditableField) => displayedFieldError(field) !== null

		const invalidFields = computed(() => editableFields.value.filter((field: AdminEditableField) => validateField(field) !== null))
		const hasInvalidFields = computed(() => invalidFields.value.length > 0)

		const helperTextForField = (field: AdminEditableField) => {
			return field.definition.type === 'number' || field.definition.type === 'date'
				? descriptionForType(field.definition.type)
				: ''
		}

		const helperMessageForField = (field: AdminEditableField) => displayedFieldError(field) ?? helperTextForField(field)

		const normaliseDraft = (field: AdminEditableField) => {
			const currentValue = field.value?.value
			if (Array.isArray(currentValue?.value)) {
				userDraftValues[field.definition.id] = [...currentValue.value]
			} else {
				userDraftValues[field.definition.id] = currentValue?.value?.toString() ?? ''
			}

			userDraftVisibilities[field.definition.id] = field.value?.current_visibility ?? definitionDefaultVisibility(field.definition)
			delete userValueErrors[field.definition.id]
		}

		const refreshFields = async(userUid: string) => {
			if (userUid === '') {
				definitions.value = []
				userValues.value = []
				clearRecord(userDraftValues)
				clearRecord(userDraftVisibilities)
				clearRecord(userValueErrors)
				return
			}

			isLoading.value = true
			errorMessage.value = ''
			successMessage.value = ''
			clearRecord(userValueErrors)

			try {
				const [loadedDefinitions, loadedValues] = await Promise.all([
					listDefinitions(),
					listAdminUserValues(userUid),
				])

				definitions.value = loadedDefinitions
				userValues.value = loadedValues
				clearRecord(userDraftValues)
				clearRecord(userDraftVisibilities)
				editableFields.value.forEach(normaliseDraft)
			} catch (error) {
				errorMessage.value = error instanceof Error ? error.message : t('profile_fields', 'Could not load profile fields for this user.')
			} finally {
				isLoading.value = false
			}
		}

		const closeDialog = () => emit('update:open', false)
		const updateOpen = (value: boolean) => emit('update:open', value)
		const visibilityOptionFor = (fieldId: number) => visibilityOptions.find((option) => option.value === userDraftVisibilities[fieldId]) ?? visibilityOptions[0]

		const updateVisibility = (fieldId: number, option: { value: FieldVisibility, label: string } | null) => {
			if (option !== null) {
				userDraftVisibilities[fieldId] = option.value
			}
			delete userValueErrors[fieldId]
		}

		const clearFieldError = (fieldId: number) => {
			delete userValueErrors[fieldId]
		}

		const formatFieldErrorMessage = (field: AdminEditableField, message: string) => {
			return ({
				'text fields expect a scalar value': t('profile_fields', '{fieldLabel} must be plain text.', { fieldLabel: field.definition.label }),
				'number fields expect a numeric value': t('profile_fields', '{fieldLabel} must be a numeric value.', { fieldLabel: field.definition.label }),
				'Date fields require a valid ISO-8601 date in YYYY-MM-DD format.': t('profile_fields', '{fieldLabel} must be a valid date in YYYY-MM-DD format.', { fieldLabel: field.definition.label }),
				'current_visibility is not supported': t('profile_fields', 'The selected visibility is not supported.'),
			}[message] ?? (message.includes('is not a valid option')
				? t('profile_fields', '{fieldLabel}: invalid option selected.', { fieldLabel: field.definition.label })
				// TRANSLATORS "{fieldLabel}" is the field label and "{message}" is a backend error message.
				: t('profile_fields', '{fieldLabel}: {message}', { fieldLabel: field.definition.label, message })))
		}

		const extractApiMessage = (error: unknown) => {
			if (!(error instanceof Error)) {
				return null
			}

			const maybeResponse = error as Error & {
				response?: {
					data?: {
						message?: string,
						ocs?: {
							meta?: { message?: string },
							data?: { message?: string },
						},
					},
				}
			}

			return maybeResponse.response?.data?.message
				?? maybeResponse.response?.data?.ocs?.data?.message
				?? maybeResponse.response?.data?.ocs?.meta?.message
				?? error.message
		}

		const payloadsMatch = (
			left: { value?: string | number | string[] | null, visibility: FieldVisibility },
			right: { value?: string | number | string[] | null, visibility: FieldVisibility },
		) => left.visibility === right.visibility
			&& JSON.stringify(left.value ?? null) === JSON.stringify(right.value ?? null)

		const buildPayload = (field: AdminEditableField) => {
			const current = userDraftValues[field.definition.id]
			const rawValue = typeof current === 'string' ? current.trim() : ''

			if (field.definition.type === 'number') {
				if (rawValue === '') {
					return { value: null, visibility: userDraftVisibilities[field.definition.id] }
				}

				if (!plainNumberPattern.test(rawValue)) {
					throw new Error(t('profile_fields', 'Numeric fields only accept plain numbers.'))
				}

				const numericValue = Number(rawValue)

				return { value: numericValue, visibility: userDraftVisibilities[field.definition.id] }
			}

			if (field.definition.type === 'date') {
				return {
					value: rawValue === '' ? null : rawValue,
					visibility: userDraftVisibilities[field.definition.id],
				}
			}

			if (field.definition.type === 'multiselect') {
				const selectedValues = userDraftValues[field.definition.id]
				if (!Array.isArray(selectedValues)) {
					throw new Error(t('profile_fields', 'Multiselect fields must use predefined options.'))
				}

				return {
					value: selectedValues,
					visibility: userDraftVisibilities[field.definition.id],
				}
			}

			return {
				value: rawValue === '' ? null : rawValue,
				visibility: userDraftVisibilities[field.definition.id],
			}
		}


		const currentPayload = (field: AdminEditableField) => {
			return {
				value: field.value?.value?.value ?? null,
				visibility: field.value?.current_visibility ?? definitionDefaultVisibility(field.definition),
			}
		}

		const hasPendingChanges = computed(() => editableFields.value.some((field: AdminEditableField) => {
			try {
				return !payloadsMatch(buildPayload(field), currentPayload(field))
			} catch {
				return true
			}
		}))

		const saveField = async(field: AdminEditableField) => {
			const fieldId = field.definition.id
			const validationError = validateField(field)
			if (validationError !== null) {
				userValueErrors[fieldId] = validationError
				return
			}

			savingIds.value = [...savingIds.value, fieldId]
			delete userValueErrors[fieldId]

			try {
				const saved = await upsertAdminUserValue(props.userUid, fieldId, buildPayload(field))
				const nextValues = userValues.value.filter((value: FieldValueRecord) => value.field_definition_id !== fieldId)
				nextValues.push(saved)
				userValues.value = nextValues
				normaliseDraft({ definition: field.definition, value: saved })
			} catch (error) {
				userValueErrors[fieldId] = formatFieldErrorMessage(field, extractApiMessage(error) ?? t('profile_fields', 'Could not save this field value. Please try again.'))
			} finally {
				savingIds.value = savingIds.value.filter((value: number) => value !== fieldId)
			}
		}

		const saveAllFields = async() => {
			const changedFields = editableFields.value.filter((field: AdminEditableField) => {
				try {
					return !payloadsMatch(buildPayload(field), currentPayload(field))
				} catch {
					return true
				}
			})

			if (changedFields.length === 0) {
				return
			}

			successMessage.value = ''
			errorMessage.value = ''
			clearRecord(userValueErrors)

			const invalidChangedFields = changedFields.filter((field: AdminEditableField) => validateField(field) !== null)
			if (invalidChangedFields.length > 0) {
				invalidChangedFields.forEach((field: AdminEditableField) => {
					const validationError = validateField(field)
					if (validationError !== null) {
						userValueErrors[field.definition.id] = validationError
					}
				})

				errorMessage.value = n('profile_fields', 'Fix the invalid field before saving.', 'Fix the invalid fields before saving.', invalidChangedFields.length, {
					count: invalidChangedFields.length,
				})
				return
			}

			for (const field of changedFields) {
				await saveField(field)
			}

			const hasFieldErrors = changedFields.some((field: AdminEditableField) => Boolean(userValueErrors[field.definition.id]))
			if (!hasFieldErrors) {
				// TRANSLATORS "{userUid}" is the account ID whose fields were saved.
				successMessage.value = t('profile_fields', 'Saved profile fields for {userUid}.', { userUid: props.userUid })
			} else {
				errorMessage.value = n('profile_fields', 'The field could not be saved.', 'Some fields could not be saved. Review the messages below.', changedFields.length, {
					count: changedFields.length,
				})
			}
		}

		watch(
			() => [props.open, props.userUid] as const,
			([open, userUid]: readonly [boolean, string]) => {
				if (!open) {
					errorMessage.value = ''
					successMessage.value = ''
					clearRecord(userValueErrors)
					return
				}

				void refreshFields(userUid)
			},
			{ immediate: true },
		)

		return {
			t,
			closeDialog,
			editableFields,
			errorMessage,
			headerDescription,
			headerUserName,
			loadingMessage,
			visibilityFieldLabel,
			hasPendingChanges,
			hasInvalidFields,
			helperTextForField,
			isLoading,
			isSavingAny,
				componentInputTypeForField,
			inputModeForField,
			placeholderForField,
			saveAllFields,
			successMessage,
			updateOpen,
			updateVisibility,
			clearFieldError,
			displayedFieldError,
			fieldHasError,
			helperMessageForField,
			validationErrorForField,
			userDraftValues,
			userDraftVisibilities,
			userValueErrors,
			visibilityOptionFor,
			visibilityOptions,
			selectOptionsFor,
			selectOptionFor,
			multiselectOptionValuesFor,
			updateSelectValue,
			updateMultiSelectValue,
		}
	},
})
</script>

<style scoped lang="scss">
.profile-fields-user-dialog {
	display: grid;
	gap: 16px;
	color: var(--color-main-text);

	&__header {
		display: flex;
		align-items: center;
		gap: 14px;
		padding: 0 0 6px;
		border-bottom: 1px solid color-mix(in srgb, var(--color-border-default) 86%, transparent);

		h2 {
			margin: 0;
			font-size: 24px;
			line-height: 1.2;
			word-break: break-word;
		}
	}

	&__header-copy-block {
		display: grid;
		gap: 2px;
		min-width: 0;
	}

	&__header-copy {
		margin: 0;
		max-width: 72ch;
		line-height: 1.4;
		color: var(--color-text-maxcontrast);
	}

	&__loading {
		display: inline-flex;
		align-items: center;
		gap: 12px;
		padding: 8px 0;
	}

	&__list {
		display: grid;
		column-gap: 10px;
		row-gap: 8px;
		grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
	}

	&__row {
		display: flex;
		flex-direction: column;
		gap: 10px;
		padding: 10px 10px;
		min-width: 0;
		height: 100%;
		border: 0;
		background: transparent;

		&--error {
			border-radius: 12px;
			background: color-mix(in srgb, var(--color-error) 6%, transparent);
		}
	}

	&__row-header {
		display: flex;
		align-items: center;
		color: var(--color-main-text);
		font-size: 16px;
		font-weight: normal;
		gap: 10px;
		margin: 12px 0 0;
		width: 100%;
	}

	&__field-label {
		margin: 0;
		font-size: 16px;
		font-weight: 500;
		line-height: 1.2;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__row-body {
		display: grid;
		gap: 8px;
		min-width: 0;
	}

	&__input {
		min-width: 0;

		:deep(.input-field__main-wrapper),
		:deep(.input-field__input),
		:deep(input) {
			width: 100%;
		}
	}

	&__visibility-control {
		min-width: 0;
		display: grid;
		grid-template-columns: minmax(0, 1fr);
		align-items: stretch;
		gap: 6px;
		padding-top: 0;

		:deep(.multiselect__tags) {
			min-height: 34px;
		}

		:deep(.multiselect),
		:deep(.input-field__main-wrapper) {
			width: 100%;
			min-width: 0;
		}

		&--error {
			:deep(.multiselect),
			:deep(.multiselect__tags),
			:deep(.multiselect__single),
			:deep(.multiselect__select) {
				color: var(--color-error-text, var(--color-error));
			}

			:deep(.multiselect__tags) {
				box-shadow: inset 0 0 0 2px color-mix(in srgb, var(--color-error) 70%, transparent);
				border-radius: var(--border-radius-element);
			}
		}
	}

	&__input-block {
		min-width: 0;
	}

	&__control-label {
		display: block;
		min-height: 0;
		font-size: 14px;
		font-weight: 400;
		line-height: 1.4;
		color: var(--color-text-maxcontrast);
		overflow-wrap: anywhere;
	}
}

@media (max-width: 700px) {
	.profile-fields-user-dialog {
		&__header {
			align-items: start;

			h2 {
				font-size: 24px;
			}
		}

		&__list {
			grid-template-columns: 1fr;
		}

		&__visibility-control {
			gap: 4px;
		}
	}
}
</style>
