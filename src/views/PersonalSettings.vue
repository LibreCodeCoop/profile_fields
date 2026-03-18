<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section
		class="profile-fields-personal profile-fields-personal--compact"
		data-testid="profile-fields-personal"
		:class="{ 'profile-fields-personal--embedded': embedded }">
		<header v-if="!embedded" class="profile-fields-personal__hero">
			<div>
				<h2>Additional profile information</h2>
			</div>
		</header>

		<NcNoteCard v-if="globalError" type="error">
			{{ globalError }}
		</NcNoteCard>

		<div v-if="isLoading" class="profile-fields-personal__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<NcEmptyContent v-else-if="sortedFields.length === 0" name="No profile fields" description="There are no user-visible profile fields assigned to your account yet." />

		<div v-else class="profile-fields-personal__grid" :class="{ 'profile-fields-personal__grid--embedded': embedded }">
			<article
				v-for="(field, index) in sortedFields"
				:key="field.definition.id"
				:id="embedded && index === 0 ? 'profile-fields-personal-info' : undefined"
				:data-save-state="fieldSaveState(field.definition.id)"
				:data-testid="`profile-fields-personal-field-${field.definition.field_key}`"
				class="profile-fields-personal__card"
				:class="{ 'profile-fields-personal__card--embedded': embedded }">
				<header v-if="embedded && !field.can_edit" class="profile-fields-personal__embedded-card-header">
					<div class="profile-fields-personal__readonly-summary">
						<div class="profile-fields-personal__readonly-copy">
							<span class="profile-fields-personal__readonly-title">{{ field.definition.label }}</span>
							<span class="profile-fields-personal__readonly-separator" aria-hidden="true">:</span>
							<span class="profile-fields-personal__readonly-text">{{ resolvedDisplayValue(field) }}</span>
							<NcPopover placement="bottom" :triggers="['hover', 'focus']" :popover-triggers="['hover']" no-focus-trap>
								<template #trigger="{ attrs }">
									<span v-bind="attrs" class="profile-fields-personal__info-trigger" :aria-label="`${field.definition.label}: managed by administrators`">
										<NcIconSvgWrapper :path="mdiInformationOutline" :size="14" />
									</span>
								</template>
								<p class="profile-fields-personal__popover-copy">Managed by administrators.</p>
							</NcPopover>
						</div>
					</div>
				</header>

				<div class="profile-fields-personal__embedded-row">
					<div v-if="!embedded || field.can_edit" class="profile-fields-personal__embedded-label">
						<div class="profile-fields-personal__label-copy">
							<label :for="field.can_edit ? fieldInputId(field.definition.id) : undefined">{{ field.definition.label }}</label>
							<NcPopover v-if="!embedded && !field.can_edit" placement="end" :triggers="['hover', 'focus']" :popover-triggers="['hover']" no-focus-trap>
								<template #trigger="{ attrs }">
									<span v-bind="attrs" class="profile-fields-personal__chip-trigger" aria-label="Read-only field information">
										<span class="profile-fields-personal__readonly-indicator" aria-hidden="true">
											<NcIconSvgWrapper :path="mdiLockOutline" :size="12" />
										</span>
									</span>
								</template>
								<p class="profile-fields-personal__popover-copy">Managed by administrators.</p>
							</NcPopover>
						</div>
					</div>

					<div v-if="field.can_edit" class="profile-fields-personal__embedded-content">
						<NcSelect
							v-if="field.definition.type === 'select'"
							:data-testid="`profile-fields-personal-input-${field.definition.field_key}`"
							:input-id="fieldInputId(field.definition.id)"
							class="profile-fields-personal__input-control profile-fields-personal__input-control--embedded"
							:model-value="selectOptionFor(field)"
							:input-label="field.definition.label"
							label-outside
							:clearable="true"
							:searchable="false"
							:options="selectOptionsFor(field.definition)"
							label="label"
							:placeholder="placeholderForField(field)"
							@update:model-value="updateSelectValue(field.definition.id, $event)"
						/>
						<NcInputField
							v-else
							:data-testid="`profile-fields-personal-input-${field.definition.field_key}`"
							:id="fieldInputId(field.definition.id)"
							class="profile-fields-personal__input-control profile-fields-personal__input-control--embedded"
							:model-value="draftValues[field.definition.id]"
							:label="field.definition.label"
							:error="fieldHasError(field.definition.id)"
							:helper-text="fieldHelperText(field.definition.id)"
							label-outside
							:type="componentInputTypeForType(field.definition.type)"
							:inputmode="inputModeForType(field.definition.type)"
							:placeholder="placeholderForField(field)"
							:success="fieldSaveSucceeded(field.definition.id)"
							@update:model-value="updateDraftValue(field.definition.id, $event)"
						/>
						<span class="profile-fields-personal__sr-only" aria-atomic="true" aria-live="polite">
							{{ autosaveAnnouncement(field) }}
						</span>

						<div v-if="!embedded" class="profile-fields-personal__embedded-toolbar">
							<label class="profile-fields-personal__embedded-visibility-label" :for="`profile-fields-personal-visibility-${field.definition.id}`">Visibility</label>
							<NcSelect
								:input-id="`profile-fields-personal-visibility-${field.definition.id}`"
								class="profile-fields-personal__visibility-select"
								:clearable="false"
								:searchable="false"
								:options="visibilityOptions"
								:input-label="visibilityInputLabel(field.definition.label)"
								label-outside
								label="label"
								:model-value="visibilityOptionFor(field.definition.id)"
								@update:model-value="updateVisibility(field.definition.id, $event)"
							/>

							<NcButton variant="primary" :disabled="isSaving(field.definition.id) || !hasFieldChanges(field)" @click="saveField(field)">
								{{ isSaving(field.definition.id) ? 'Saving...' : 'Save' }}
							</NcButton>
						</div>

					</div>
				</div>
			</article>
		</div>

		<Teleport v-if="embedded && embeddedVisibilityAnchorReady && editableVisibilityFields.length > 0" to="#profile-fields-personal-visibility-anchor">
			<div class="profile-fields-personal__visibility-teleport-root" data-testid="profile-fields-personal-visibility-panel" role="group" :aria-labelledby="visibilitySectionHeadingId" :aria-describedby="visibilitySectionDescriptionId">
				<h3 :id="visibilitySectionHeadingId" class="profile-fields-personal__sr-only">Additional profile fields visibility</h3>
				<p :id="visibilitySectionDescriptionId" class="profile-fields-personal__sr-only">Choose who can see each custom profile field on your profile.</p>
				<div
					v-for="field in editableVisibilityFields"
					:key="`embedded-visibility-${field.definition.id}`"
					:data-testid="`profile-fields-personal-visibility-${field.definition.field_key}`"
					class="profile-fields-personal__native-visibility-row">
					<label class="profile-fields-personal__native-visibility-label" :for="`profile-fields-personal-visibility-${field.definition.id}`">
						{{ field.definition.label }}
					</label>
					<NcSelect
						:input-id="`profile-fields-personal-visibility-${field.definition.id}`"
						class="profile-fields-personal__native-visibility-select"
						:clearable="false"
						:searchable="false"
						:options="visibilityOptions"
						:input-label="visibilityInputLabel(field.definition.label)"
						label-outside
						label="label"
						:model-value="visibilityOptionFor(field.definition.id)"
						@update:model-value="updateVisibility(field.definition.id, $event)"
					/>
				</div>
			</div>
		</Teleport>

		<section
			v-else-if="embedded && editableVisibilityFields.length > 0"
			class="profile-fields-personal__visibility-panel"
			role="group"
			:aria-labelledby="visibilitySectionHeadingId"
			:aria-describedby="visibilitySectionDescriptionId"
			data-testid="profile-fields-personal-visibility-panel">
			<h3 :id="visibilitySectionHeadingId" class="profile-fields-personal__sr-only">Additional profile fields visibility</h3>
			<p :id="visibilitySectionDescriptionId" class="profile-fields-personal__sr-only">Choose who can see each custom profile field on your profile.</p>
			<div class="profile-fields-personal__visibility-grid">
				<div
					v-for="field in editableVisibilityFields"
					:key="`fallback-visibility-${field.definition.id}`"
					:data-testid="`profile-fields-personal-visibility-${field.definition.field_key}`"
					class="profile-fields-personal__native-visibility-row">
					<label class="profile-fields-personal__native-visibility-label" :for="`profile-fields-personal-visibility-${field.definition.id}`">
						{{ field.definition.label }}
					</label>
					<NcSelect
						:input-id="`profile-fields-personal-visibility-${field.definition.id}`"
						class="profile-fields-personal__native-visibility-select"
						:clearable="false"
						:searchable="false"
						:options="visibilityOptions"
						:input-label="visibilityInputLabel(field.definition.label)"
						label-outside
						label="label"
						:model-value="visibilityOptionFor(field.definition.id)"
						@update:model-value="updateVisibility(field.definition.id, $event)"
					/>
				</div>
			</div>
		</section>
	</section>
</template>

<script setup lang="ts">
import { mdiLockOutline, mdiInformationOutline } from '@mdi/js'
import { computed, inject, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { NcButton, NcEmptyContent, NcIconSvgWrapper, NcInputField, NcLoadingIcon, NcNoteCard, NcPopover, NcSelect } from '@nextcloud/vue'
import { listEditableFields, upsertOwnValue } from '../api'
import type { EditableField, FieldType, FieldVisibility } from '../types'
import { visibilityOptions } from '../utils/visibilityOptions.js'

const embedded = inject<boolean>('profileFieldsEmbedded', false)
const visibilitySectionHeadingId = 'profile-fields-personal-visibility-heading'
const visibilitySectionDescriptionId = 'profile-fields-personal-visibility-description'

const fields = ref<EditableField[]>([])
const isLoading = ref(true)
const globalError = ref('')
const savingIds = ref<number[]>([])
const successIds = ref<number[]>([])
const fieldErrors = reactive<Record<number, string>>({})
const autoSaveTimers = new Map<number, number>()
const embeddedVisibilityAnchorReady = ref(false)

const draftValues = reactive<Record<number, string>>({})
const draftVisibilities = reactive<Record<number, FieldVisibility>>({})

const inputModesByType: Record<FieldType, 'text' | 'decimal'> = {
	text: 'text',
	number: 'decimal',
	select: 'text',
}

const inputModeForType = (type: FieldType): 'text' | 'decimal' => {
	return inputModesByType[type]
}

const fieldInputId = (fieldId: number) => `profile-fields-personal-value-${fieldId}`

const componentInputTypesByType: Record<FieldType, 'text' | 'number'> = {
	text: 'text',
	number: 'number',
	select: 'text',
}

const componentInputTypeForType = (type: FieldType): 'text' | 'number' => {
	return componentInputTypesByType[type]
}

const placeholderForField = (field: EditableField) => {
	if (!embedded) {
		return ''
	}

	if (field.definition.type === 'number') {
		return 'Enter a number'
	}

	if (field.definition.type === 'select') {
		return 'Choose a value'
	}

	return 'Enter a value'
}

const normaliseEditableField = (field: EditableField) => {
	const existingValue = field.value?.value
	draftValues[field.definition.id] = existingValue?.value?.toString() ?? ''
	draftVisibilities[field.definition.id] = field.value?.current_visibility ?? field.definition.initial_visibility
	delete fieldErrors[field.definition.id]
}

const loadFields = async() => {
	isLoading.value = true
	globalError.value = ''
	try {
		fields.value = await listEditableFields()
		fields.value.forEach(normaliseEditableField)
	} catch (error) {
		globalError.value = error instanceof Error ? error.message : 'Failed to load profile fields.'
	} finally {
		isLoading.value = false
	}
}

const isSaving = (fieldId: number) => savingIds.value.includes(fieldId)
const isSaved = (fieldId: number) => successIds.value.includes(fieldId)
const fieldHasError = (fieldId: number) => Boolean(fieldErrors[fieldId])
const fieldHelperText = (fieldId: number) => fieldErrors[fieldId] ?? ''
const fieldSaveSucceeded = (fieldId: number) => isSaved(fieldId) && !fieldHasError(fieldId)
const visibilityInputLabel = (fieldLabel: string) => `Visibility for ${fieldLabel}`
const fieldSaveState = (fieldId: number) => {
	if (fieldHasError(fieldId)) {
		return 'error'
	}

	if (fieldSaveSucceeded(fieldId)) {
		return 'success'
	}

	if (isSaving(fieldId)) {
		return 'saving'
	}

	return 'idle'
}
const visibilityOptionFor = (fieldId: number) => visibilityOptions.find((option) => option.value === draftVisibilities[fieldId]) ?? visibilityOptions[0]
const currentDraftValue = (field: EditableField) => draftValues[field.definition.id]
const autosaveAnnouncement = (field: EditableField) => {
	const fieldId = field.definition.id
	if (fieldHasError(fieldId)) {
		return `${field.definition.label}: ${fieldHelperText(fieldId)}`
	}

	if (fieldSaveSucceeded(fieldId)) {
		return `${field.definition.label} saved.`
	}

	if (isSaving(fieldId)) {
		return `Saving ${field.definition.label}.`
	}

	return ''
}

const currentStoredValue = (field: EditableField) => {
	const existingValue = field.value?.value as unknown
	if (existingValue === null || existingValue === undefined) {
		return ''
	}

	if (typeof existingValue === 'string' || typeof existingValue === 'number') {
		return String(existingValue)
	}

	if (typeof existingValue !== 'object') {
		return ''
	}

	return 'value' in existingValue && existingValue.value !== null && existingValue.value !== undefined
		? String(existingValue.value)
		: ''
}

const resolvedDisplayValue = (field: EditableField) => {
	const value = currentStoredValue(field) || draftValues[field.definition.id] || ''
	return value === '' ? 'No value available.' : value
}

const findField = (fieldId: number) => fields.value.find((field: EditableField) => field.definition.id === fieldId)

const canAutosaveField = (field: EditableField) => {
	const rawValue = draftValues[field.definition.id]

	if (rawValue === '') {
		return true
	}

	if (field.definition.type === 'text' || field.definition.type === 'select') {
		return true
	}

	if (field.definition.type === 'number') {
		return !Number.isNaN(Number(rawValue))
	}

	return false
}

const queueSuccessState = (fieldId: number) => {
	successIds.value = [...successIds.value.filter((id: number) => id !== fieldId), fieldId]
	window.setTimeout(() => {
		successIds.value = successIds.value.filter((id: number) => id !== fieldId)
	}, embedded ? 1600 : 2400)
}

const hasFieldChanges = (field: EditableField) => {
	if (!field.can_edit) {
		return false
	}

	const visibilityChanged = draftVisibilities[field.definition.id] !== (field.value?.current_visibility ?? field.definition.initial_visibility)
	return visibilityChanged || currentDraftValue(field) !== currentStoredValue(field)
}

const updateDraftValue = (fieldId: number, value: string | number | null) => {
	draftValues[fieldId] = value === null ? '' : value.toString()
	delete fieldErrors[fieldId]
	const existingTimer = autoSaveTimers.get(fieldId)
	if (existingTimer !== undefined) {
		window.clearTimeout(existingTimer)
		autoSaveTimers.delete(fieldId)
	}

	if (!embedded) {
		return
	}

	const field = findField(fieldId)
	if (field === undefined || !field.can_edit || !hasFieldChanges(field) || !canAutosaveField(field)) {
		return
	}

	autoSaveTimers.set(fieldId, window.setTimeout(() => {
		autoSaveTimers.delete(fieldId)
		void saveField(field)
	}, 900))
}

const updateVisibility = (fieldId: number, option: { value: FieldVisibility, label: string } | null) => {
	if (option !== null) {
		draftVisibilities[fieldId] = option.value
	}
	delete fieldErrors[fieldId]
	const existingTimer = autoSaveTimers.get(fieldId)
	if (existingTimer !== undefined) {
		window.clearTimeout(existingTimer)
		autoSaveTimers.delete(fieldId)
	}

	if (!embedded) {
		return
	}

	const field = findField(fieldId)
	if (field === undefined || !field.can_edit || !hasFieldChanges(field)) {
		return
	}

	autoSaveTimers.set(fieldId, window.setTimeout(() => {
		autoSaveTimers.delete(fieldId)
		void saveField(field)
	}, 250))
}

const buildPayload = (field: EditableField) => {
	const rawValue = draftValues[field.definition.id]
	const currentVisibility = draftVisibilities[field.definition.id]

	if (field.definition.type === 'number') {
		return {
			value: rawValue === '' ? null : Number(rawValue),
			currentVisibility,
		}
	}

	return {
		value: rawValue === '' ? null : rawValue,
		currentVisibility,
	}
}

const selectOptionsFor = (definition: { options: string[] | null }) =>
	(definition.options ?? []).map((opt: string) => ({ value: opt, label: opt }))

const selectOptionFor = (field: EditableField) => {
	const value = draftValues[field.definition.id]
	return value ? { value, label: value } : null
}

const updateSelectValue = (fieldId: number, option: { value: string, label: string } | null) => {
	updateDraftValue(fieldId, option?.value ?? '')
}

const saveField = async(field: EditableField) => {
	const fieldId = field.definition.id
	if (!hasFieldChanges(field)) {
		return
	}

	fieldErrors[fieldId] = ''
	successIds.value = successIds.value.filter((id: number) => id !== fieldId)
	savingIds.value = [...savingIds.value, fieldId]

	try {
		const saved = await upsertOwnValue(fieldId, buildPayload(field))
		field.value = saved
		normaliseEditableField(field)
		queueSuccessState(fieldId)
	} catch (error: any) {
		fieldErrors[fieldId] = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to save this field.'
	} finally {
		savingIds.value = savingIds.value.filter((id: number) => id !== fieldId)
	}
}

const sortedFields = computed(() => [...fields.value].sort((left, right) => left.definition.sort_order - right.definition.sort_order || left.definition.id - right.definition.id))
const editableVisibilityFields = computed(() => sortedFields.value.filter((field: EditableField) => field.can_edit))

const syncEmbeddedVisibilityAnchorReady = () => {
	embeddedVisibilityAnchorReady.value = document.querySelector('#profile-fields-personal-visibility-anchor') !== null
}

onMounted(() => {
	void loadFields()
	if (!embedded) {
		return
	}

	syncEmbeddedVisibilityAnchorReady()
	window.addEventListener('profile-fields:embedded-visibility-anchor-ready', syncEmbeddedVisibilityAnchorReady)
	window.addEventListener('load', syncEmbeddedVisibilityAnchorReady, { once: true, passive: true })
})

onBeforeUnmount(() => {
	if (!embedded) {
		return
	}

	window.removeEventListener('profile-fields:embedded-visibility-anchor-ready', syncEmbeddedVisibilityAnchorReady)
	for (const timerId of autoSaveTimers.values()) {
		window.clearTimeout(timerId)
	}
	autoSaveTimers.clear()
})
</script>

<style lang="scss">
.profile-fields-personal__profile-anchor {
	display: block;
	height: 44px;
	width: min(100%, 290px);
	overflow: hidden;
	text-overflow: ellipsis;
	line-height: 44px;
	padding: 0 16px 0 44px;
	margin: 0 auto 14px;
	border-radius: var(--border-radius-pill);
	color: var(--color-text-maxcontrast);
	background-color: transparent;
	position: relative;

	&::before {
		content: '';
		position: absolute;
		inset-inline-start: 16px;
		top: 14px;
		width: 10px;
		height: 10px;
		border-inline-end: 2px solid currentColor;
		border-bottom: 2px solid currentColor;
		transform: rotate(45deg);
		opacity: 0.9;
	}

	&:hover,
	&:focus,
	&:active {
		color: var(--color-main-text);
		background-color: var(--color-background-dark);
	}
}

#personal-settings.profile-fields-personal-info-grid {
	align-items: start;
}

#personal-settings.profile-fields-personal-info-grid .profile-fields-personal-info-box {
	grid-column: 1 / -1;
}

#personal-settings.profile-fields-personal-info-grid .personal-settings-setting-box:has(#account-property-biography),
#personal-settings.profile-fields-personal-info-stacked .personal-settings-setting-box:has(#account-property-biography) {
	min-block-size: 260px;
}

#personal-settings.profile-fields-personal-info-grid .personal-settings-setting-box:has(#account-property-biography) #account-property-biography,
#personal-settings.profile-fields-personal-info-stacked .personal-settings-setting-box:has(#account-property-biography) #account-property-biography {
	min-block-size: 176px;
}

.profile-fields-personal-info-box--stacked #profile-fields-personal-info-settings > .profile-fields-personal {
	display: block;
}

.profile-fields-personal-info-box--stacked #profile-fields-personal-info-settings > .profile-fields-personal .profile-fields-personal__grid--embedded {
	display: grid;
	grid-template-columns: 1fr;
	gap: 18px;
}

.profile-fields-personal-info-box {
	display: block;
	width: 100%;
	max-width: 100%;
	min-width: 0;
	align-self: start;
	box-sizing: border-box;
}

.profile-fields-personal-info-box--stacked {
	display: block;
	width: 100%;
	max-width: 100%;
	padding: 0 20px 20px;
	box-sizing: border-box;
}

.profile-fields-personal-info-box--stacked #profile-fields-personal-info-settings > .profile-fields-personal .profile-fields-personal__card--embedded {
	padding: 0 10px;
	border: 0;
	border-radius: 0;
	background: transparent;
	box-shadow: none;
	max-inline-size: none;
}

</style>

<style scoped lang="scss">
.profile-fields-personal {
	display: grid;
	gap: 18px;

	&--compact {
		gap: 16px;
		padding-top: 4px;
		box-sizing: border-box;
		max-width: 720px;
		width: 100%;
	}

	&--embedded {
		display: block;
		width: 100%;
		max-width: 100%;
	}

	&__hero {
		display: flex;
		justify-content: space-between;
		gap: 24px;
		padding: 24px;
		border-radius: 20px;
		background:
			radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary-element) 20%, transparent), transparent 34%),
			linear-gradient(135deg, color-mix(in srgb, var(--color-background-darker) 72%, var(--color-main-background) 28%), color-mix(in srgb, var(--color-main-background) 90%, var(--color-primary-element) 10%));
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 24%, var(--color-border-default) 76%);
		box-shadow: 0 18px 48px rgba(15, 23, 42, 0.14);

		> div:first-child {
			padding-inline-start: clamp(28px, 4vw, 44px);
		}

		h2 {
			margin: 0 0 8px;
			font-size: 28px;
		}

		p {
			margin: 0;
			max-width: 60ch;
			color: color-mix(in srgb, var(--color-main-text) 82%, transparent);
		}
	}

	&__grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 18px;

		&--embedded {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(min(100%, 300px), 1fr));
		}
	}

	&__card {
		display: grid;
		gap: 16px;
		padding: 20px;
		border-radius: 20px;
		background: color-mix(in srgb, var(--color-main-background) 96%, var(--color-background-dark) 4%);
		border: 1px solid color-mix(in srgb, var(--color-border-default) 84%, transparent);
		box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);

		&--embedded {
			gap: 12px;
			padding: 10px;
			border-radius: 0;
			background: transparent;
			border: 0;
			box-shadow: none;
			align-self: start;
			inline-size: 100%;
			max-inline-size: none;
		}
	}

	&__embedded-card-header {
		display: grid;
		gap: 8px;

		h3 {
			margin: 0;
			font-size: 16px;
			line-height: 1.3;
		}
	}

	&__card-header {
		display: flex;
		justify-content: space-between;
		gap: 12px;
		align-items: flex-start;

		h3 {
			margin: 0;
			font-size: 20px;

		}

		p {
			margin: 6px 0 0;
			color: var(--color-text-maxcontrast);
		}
	}

	&__section-heading {
		h4 {
			margin: 0;
			font-size: 16px;
		}

		p {
			margin: 6px 0 0;
			color: var(--color-text-maxcontrast);
		}
	}

	&__tag {
		align-self: start;
		padding: 6px 10px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--color-primary-element) 12%, transparent);
		font-size: 12px;
		font-weight: 700;
		text-transform: uppercase;

		.profile-fields-personal__card--embedded & {
			padding: 4px 8px;
			font-size: 11px;
		}
	}

	&__embedded-row {
		display: grid;
		grid-template-columns: 1fr;
		gap: 12px;
		align-items: start;
	}

	&__embedded-label {
		padding-top: 0;

		label {
			display: block;
			color: var(--color-main-text);
			font-size: 16px;
			font-weight: 400;
			line-height: 1.5;
		}
	}

	&__label-copy {
		display: inline-flex;
		align-items: center;
		gap: 8px;
	}

	&__embedded-content {
		display: grid;
		gap: 10px;
		max-width: none;
	}

	&__readonly-summary {
		display: block;
	}

	&__readonly-copy {
		display: inline-flex;
		flex-wrap: wrap;
		align-items: baseline;
		gap: 6px;
		min-width: 0;
	}

	&__readonly-title {
		font-size: 16px;
		font-weight: 400;
		color: var(--color-main-text);
	}

	&__readonly-separator {
		color: var(--color-main-text);
	}

	&__readonly-text {
		color: var(--color-main-text);
		font-weight: 600;
		font-variant-numeric: tabular-nums;
		opacity: 1;
		word-break: break-word;
	}

	&__info-trigger {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		color: var(--color-text-maxcontrast);
		cursor: help;
		inline-size: 18px;
		block-size: 18px;
		margin-inline-start: 4px;
		vertical-align: text-bottom;

		&:hover,
		&:focus,
		&:active {
			color: var(--color-main-text);
		}
	}

	&__chip-trigger {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		cursor: help;
	}

	&__readonly-indicator {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 18px;
		height: 18px;
		border-radius: 999px;
		border: 1px solid color-mix(in srgb, var(--color-text-maxcontrast) 42%, transparent);
		background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-dark) 8%);
		color: var(--color-text-maxcontrast);
		line-height: 1;

		:deep(.material-design-icon) {
			width: 12px;
			height: 12px;
		}
	}

	&__readonly-value {
		min-height: 34px;
		padding: 8px 0;
		line-height: 1.5;
		color: var(--color-main-text);
	}

	&__embedded-toolbar {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: 10px;
	}

	&__embedded-visibility-label {
		color: var(--color-main-text);
		font-size: 16px;
		font-weight: 400;
		line-height: 1.5;
	}

	&__popover-copy {
		margin: 0;
		max-width: 240px;
		font-size: 13px;
		line-height: 1.45;
	}

	&__sr-only {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0, 0, 0, 0);
		white-space: nowrap;
		border: 0;
	}

	&__visibility-select {
		width: min(100%, 220px);
	}

	&__visibility-panel {
		display: grid;
		padding-top: 22px;
	}

	&__visibility-teleport-root {
		display: contents;
	}

	&__visibility-grid {
		display: grid;
		gap: 12px;
	}

	&__native-visibility-row {
		display: flex;
		flex-wrap: wrap;
	}

	&__native-visibility-label {
		color: var(--color-text-maxcontrast);
		line-height: 50px;
		width: 150px;
	}

	&__field-grid {
		display: grid;
		gap: 14px;

		&--embedded {
			grid-template-columns: minmax(0, 420px) 270px;
			align-items: end;
			justify-content: start;
			gap: 12px 20px;
		}
	}

	&__input-control,
	&__visibility-control {
		min-width: 0;

		label {
			display: grid;
			gap: 8px;
			font-weight: 600;
		}

		:deep(.input-field__main-wrapper),
		:deep(.multiselect) {
			width: 100%;
			min-width: 0;
		}
	}

	&__input-control {
		&--embedded {
			:deep(.input-field) {
				margin-bottom: 0;
			}

			:deep(.input-field__label) {
				display: none;
			}
		}

		:deep(.input-field__helper-text) {
			.profile-fields-personal__card--embedded & {
				display: none;
			}
		}
	}

	&__visibility-control {
		display: grid;
		gap: 8px;
		align-content: start;

		label {
			font-size: 14px;

			.profile-fields-personal--embedded & {
				font-size: 13px;
				color: var(--color-text-maxcontrast);
			}
		}

		:deep(.multiselect) {
			.profile-fields-personal--embedded & {
				max-width: 270px;
			}
		}
	}

	&__native-visibility-select {
		width: 270px;
		max-width: 40vw;

		:deep(.multiselect) {
			width: 270px;
			max-width: 40vw;
		}

		:deep(.multiselect__tags) {
			min-height: 34px;
		}

		:deep(.multiselect__single) {
			line-height: 32px;
		}
	}

	&__meta-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		padding-top: 4px;

		&--embedded {
			padding-top: 2px;
		}
	}

	&__meta {
		margin: 0;
		font-size: 13px;
		color: var(--color-text-maxcontrast);

		.profile-fields-personal--embedded & {
			font-size: 12px;
			line-height: 1.4;
		}
	}

	&__actions {
		display: flex;
		justify-content: flex-end;

		.profile-fields-personal__card--embedded & {
			padding-top: 2px;
			justify-content: flex-start;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		padding: 40px 0;
	}
}

@media (max-width: 1024px) {
	.profile-fields-personal {
		&__hero {
			flex-direction: column;
		}
	}
}

@media (max-width: 720px) {
	.profile-fields-personal {
		&__hero {
			padding: 20px;

			> div:first-child {
				padding-inline-start: 36px;
			}
		}

		&__grid {
			&--embedded {
				grid-template-columns: 1fr;
			}
		}

		&__card {
			&--embedded {
				max-inline-size: none;
			}
		}

		&__embedded-toolbar {
			align-items: stretch;
		}

		&__visibility-select {
			width: 100%;

			:deep(.multiselect) {
				width: 100%;
			}
		}
	}
}
</style>
