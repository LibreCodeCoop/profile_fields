<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors

SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:open="open"
		size="large"
		:name="title"
		content-classes="profile-fields-user-dialog__content"
		@update:open="updateOpen">
		<div class="profile-fields-user-dialog">
			<header class="profile-fields-user-dialog__header">
				<NcAvatar :key="userUid" :user="userUid" :size="40" disable-menu hide-status />

				<div class="profile-fields-user-dialog__header-copy-block">
					<p class="profile-fields-user-dialog__eyebrow">User management</p>
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
				<span>Loading fields for {{ userUid }}...</span>
			</div>

			<NcEmptyContent
				v-else-if="editableFields.length === 0"
				name="No active fields"
				description="Create and activate field definitions first. They will appear here automatically." />

			<div v-else class="profile-fields-user-dialog__list">
				<article v-for="field in editableFields" :key="field.definition.id" class="profile-fields-user-dialog__row" :class="{ 'profile-fields-user-dialog__row--error': fieldHasError(field) }">
					<div class="profile-fields-user-dialog__row-header">
						<label class="profile-fields-user-dialog__field-label" :for="`profile-fields-user-dialog-value-${field.definition.id}`">{{ field.definition.label }}</label>
						<div class="profile-fields-user-dialog__badges">
							<span class="profile-fields-user-dialog__type-tag">{{ field.definition.type }}</span>
						</div>
					</div>

					<div class="profile-fields-user-dialog__row-body">
						<NcInputField
							class="profile-fields-user-dialog__input"
							:id="`profile-fields-user-dialog-value-${field.definition.id}`"
							v-model="userDraftValues[field.definition.id]"
							:aria-label="field.definition.label"
							:error="fieldHasError(field)"
							:helper-text="helperMessageForField(field)"
							label-outside
							type="text"
							:inputmode="inputModeForField(field.definition.type)"
							:placeholder="field.definition.type === 'number' ? 'Enter a number' : ''"
							@update:model-value="clearFieldError(field.definition.id)"
						/>

						<div class="profile-fields-user-dialog__visibility-control" :class="{ 'profile-fields-user-dialog__visibility-control--error': fieldHasError(field) }">
							<label class="profile-fields-user-dialog__control-label" :for="`profile-fields-user-dialog-visibility-${field.definition.id}`">Visibility</label>
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

					<div v-if="field.value !== null" class="profile-fields-user-dialog__row-footer">
						<p class="profile-fields-user-dialog__meta profile-fields-user-dialog__meta--secondary">
							Last updated by {{ field.value.updated_by_uid }} on {{ field.value.updated_at }}
						</p>
					</div>

				</article>
			</div>
		</div>

		<template #actions>
			<NcButton @click="closeDialog">
				Close
			</NcButton>
			<NcButton variant="primary" :disabled="!hasPendingChanges || hasInvalidFields || isSavingAny || isLoading" @click="saveAllFields">
				{{ isSavingAny ? 'Saving...' : 'Save' }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script lang="ts">
import { computed, defineComponent, reactive, ref, watch } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import { NcButton, NcDialog, NcEmptyContent, NcInputField, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { listAdminUserValues, listDefinitions, upsertAdminUserValue } from '../api'
import type { AdminEditableField, FieldDefinition, FieldType, FieldValueRecord, FieldVisibility } from '../types'
import { buildAdminEditableFields } from '../utils/adminFieldValues.js'

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
		const visibilityOptions: Array<{ value: FieldVisibility, label: string }> = [
			{ value: 'private', label: 'Private' },
			{ value: 'users', label: 'Authenticated users' },
			{ value: 'public', label: 'Public' },
		]

		const definitions = ref<FieldDefinition[]>([])
		const userValues = ref<FieldValueRecord[]>([])
		const isLoading = ref(false)
		const errorMessage = ref('')
		const successMessage = ref('')
		const savingIds = ref<number[]>([])

		const userValueErrors = reactive<Record<number, string>>({})
		const userDraftValues = reactive<Record<number, string>>({})
		const userDraftVisibilities = reactive<Record<number, FieldVisibility>>({})

		const title = computed(() => 'Edit profile fields')
		const headerUserName = computed(() => props.userDisplayName.trim() !== '' ? props.userDisplayName : props.userUid)
		const editableFields = computed<AdminEditableField[]>(() => buildAdminEditableFields(definitions.value, userValues.value))
		const isSavingAny = computed(() => savingIds.value.length > 0)
		const headerDescription = computed(() => {
			if (props.userUid === '') {
				return 'Change the active custom profile fields for the selected account.'
			}

			const count = editableFields.value.length
			const label = count === 1 ? '1 active field' : `${count} active fields`
			return `${label} for @${props.userUid}.`
		})

		const clearRecord = (record: Record<string | number, unknown>) => {
			Object.keys(record).forEach((key) => {
				delete record[key]
			})
		}

		const descriptionForType = (type: FieldType) => ({
			text: 'Free text stored as a scalar value.',
			number: 'Only numeric values are accepted.',
		}[type])

		const plainNumberPattern = /^-?\d+(\.\d+)?$/

		const inputModeForField = (type: FieldType) => ({
			text: 'text',
			number: 'decimal',
		}[type])

		const rawDraftValueFor = (fieldId: number) => userDraftValues[fieldId]?.trim() ?? ''

		const validateField = (field: AdminEditableField): string | null => {
			const rawValue = rawDraftValueFor(field.definition.id)

			if (rawValue === '') {
				return null
			}

			if (field.definition.type === 'number' && !plainNumberPattern.test(rawValue)) {
				return `${field.definition.label} must be a plain numeric value.`
			}

			return null
		}

		const validationErrorForField = (field: AdminEditableField) => validateField(field)
		const displayedFieldError = (field: AdminEditableField) => userValueErrors[field.definition.id] ?? validationErrorForField(field)
		const fieldHasError = (field: AdminEditableField) => displayedFieldError(field) !== null

		const invalidFields = computed(() => editableFields.value.filter((field: AdminEditableField) => validateField(field) !== null))
		const hasInvalidFields = computed(() => invalidFields.value.length > 0)

		const helperTextForField = (field: AdminEditableField) => {
			return descriptionForType(field.definition.type)
		}

		const helperMessageForField = (field: AdminEditableField) => displayedFieldError(field) ?? helperTextForField(field)

		const normaliseDraft = (field: AdminEditableField) => {
			const currentValue = field.value?.value
			userDraftValues[field.definition.id] = currentValue?.value?.toString() ?? ''

			userDraftVisibilities[field.definition.id] = field.value?.current_visibility ?? field.definition.initial_visibility
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
				errorMessage.value = error instanceof Error ? error.message : 'Failed to load profile fields for this user.'
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
				'text fields expect a scalar value': `${field.definition.label} must be plain text.`,
				'number fields expect a numeric value': `${field.definition.label} must be a numeric value.`,
				'current_visibility is not supported': 'The selected visibility is not supported.',
			}[message] ?? `${field.definition.label}: ${message}`)
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
			left: { value?: string | number | null, visibility: FieldVisibility },
			right: { value?: string | number | null, visibility: FieldVisibility },
		) => left.visibility === right.visibility
			&& (left.value ?? null) === (right.value ?? null)

		const buildPayload = (field: AdminEditableField) => {
			const rawValue = userDraftValues[field.definition.id]?.trim() ?? ''

			if (field.definition.type === 'number') {
				if (rawValue === '') {
					return { value: null, visibility: userDraftVisibilities[field.definition.id] }
				}

				if (!plainNumberPattern.test(rawValue)) {
					throw new Error('Numeric fields only accept plain numbers.')
				}

				const numericValue = Number(rawValue)

				return { value: numericValue, visibility: userDraftVisibilities[field.definition.id] }
			}

			return {
				value: rawValue === '' ? null : rawValue,
				visibility: userDraftVisibilities[field.definition.id],
			}
		}

		const currentPayload = (field: AdminEditableField) => {
			return {
				value: field.value?.value?.value ?? null,
				visibility: field.value?.current_visibility ?? field.definition.initial_visibility,
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
				userValueErrors[fieldId] = formatFieldErrorMessage(field, extractApiMessage(error) ?? 'Failed to save this field value.')
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

				errorMessage.value = invalidChangedFields.length === 1
					? 'Fix the invalid field before saving.'
					: 'Fix the invalid fields before saving.'
				return
			}

			for (const field of changedFields) {
				await saveField(field)
			}

			const hasFieldErrors = changedFields.some((field: AdminEditableField) => Boolean(userValueErrors[field.definition.id]))
			if (!hasFieldErrors) {
				successMessage.value = `Saved profile fields for ${props.userUid}.`
			} else {
				errorMessage.value = changedFields.length === 1
					? 'The field could not be saved.'
					: 'Some fields could not be saved. Review the messages below.'
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
			closeDialog,
			editableFields,
			errorMessage,
			headerDescription,
			headerUserName,
			hasPendingChanges,
			hasInvalidFields,
			helperTextForField,
			isLoading,
			isSavingAny,
			inputModeForField,
			saveAllFields,
			successMessage,
			title,
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

	&__eyebrow {
		margin: 0;
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.08em;
		text-transform: uppercase;
		color: var(--color-text-maxcontrast);
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
		gap: 8px;
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
		align-items: center;
		color: var(--color-main-text);
		display: inline-flex;
		font-size: 16px;
		font-weight: normal;
		gap: 8px;
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

	&__badges {
		display: flex;
		gap: 8px;
		align-items: center;
		flex-wrap: wrap;
		margin-inline-start: auto;
	}

	&__type-tag,
	&__status-tag {
		padding: 4px 8px;
		border-radius: 999px;
		font-size: 11px;
		font-weight: 700;
		text-transform: uppercase;
		color: var(--color-main-text);
	}

	&__type-tag {
		background: color-mix(in srgb, var(--color-primary-element) 14%, transparent);
	}

	&__status-tag {
		background: color-mix(in srgb, #c96c10 14%, transparent);
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

	&__meta {
		margin: 0;
		font-size: 12px;
		color: var(--color-text-maxcontrast);

		&--secondary {
			font-size: 12px;
		}
	}

	&__row-footer {
		margin-top: 2px;
	}

	&__visibility-control {
		min-width: 0;
		display: grid;
		gap: 6px;

		:deep(.multiselect),
		:deep(.input-field__main-wrapper) {
			width: 100%;
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
		font-size: 13px;
		font-weight: 600;
		color: var(--color-main-text);
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
	}
}
</style>
