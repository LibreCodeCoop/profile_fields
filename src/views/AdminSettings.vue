<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors

SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="profile-fields-admin" data-testid="profile-fields-admin">
		<header class="profile-fields-admin__hero">
			<div class="profile-fields-admin__hero-copy">
				<h2>{{ t('profile_fields', 'Profile fields catalog') }}</h2>
				<p>
					{{ t('profile_fields', 'Create custom profile fields, choose who can edit each one, and define the default visibility for new values.') }}
				</p>
			</div>
			<div class="profile-fields-admin__hero-meta">
				<strong>{{ definitions.length }}</strong>
				<span>{{ configuredFieldsCountLabel }}</span>
			</div>
		</header>

		<NcNoteCard v-if="errorMessage" type="error" data-testid="profile-fields-admin-error">
			{{ errorMessage }}
		</NcNoteCard>
		<AdminSupportBanner />
		<span
			class="profile-fields-admin__success-announcement"
			data-testid="profile-fields-admin-success"
			aria-live="polite"
			aria-atomic="true">{{ successMessage }}</span>

		<div v-if="isLoading" class="profile-fields-admin__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-else class="profile-fields-admin__layout">
			<aside class="profile-fields-admin__list-panel">
				<div class="profile-fields-admin__panel-header">
					<div class="profile-fields-admin__panel-header-copy">
						<h3>{{ t('profile_fields', 'Configured fields') }}</h3>
						<p>{{ t('profile_fields', 'Select a field to edit, or create a new one.') }}</p>
					</div>
					<NcButton variant="secondary" data-testid="profile-fields-admin-new-field" @click="startCreatingField">
						{{ t('profile_fields', 'Create field') }}
					</NcButton>
				</div>

				<NcEmptyContent v-if="sortedDefinitions.length === 0" :name="t('profile_fields', 'No fields configured')" :description="t('profile_fields', 'Create your first field to make it available in user profiles.')" />

				<Draggable
					v-else
					:model-value="sortedDefinitions"
					tag="ul"
					class="profile-fields-admin__list"
					item-key="id"
					handle=".profile-fields-admin__definition-handle"
					ghost-class="profile-fields-admin__list-row--ghost"
					chosen-class="profile-fields-admin__list-row--chosen"
					:animation="180"
					:disabled="isSaving"
					@change="reorderDefinitions">
					<template #item="{ element: definition }">
						<li class="profile-fields-admin__list-row" :class="{ 'is-disabled': isSaving }">
							<NcListItem
								class="profile-fields-admin__list-item"
								:class="{ 'is-selected': definition.id === selectedId, 'is-just-saved': definition.id === justSavedId }"
								:data-testid="`profile-fields-admin-definition-${definition.field_key}`"
								:name="definition.label"
								:active="definition.id === selectedId"
								compact
								:link-aria-label="editFieldAriaLabel(definition.label)"
								@click="handleDefinitionClick(definition)">
								<template #subname>
									<span class="profile-fields-admin__list-item-subname">{{ definition.field_key }}</span>
								</template>
								<template #extra-actions>
									<NcChip
										class="profile-fields-admin__definition-status"
										:text="definition.active ? t('profile_fields', 'Active') : t('profile_fields', 'Inactive')"
										:variant="definition.active ? 'success' : 'secondary'"
										:no-close="true" />
									<NcActions
										class="profile-fields-admin__definition-actions"
										:aria-label="actionsForLabel(definition.label)">
										<NcActionButton :disabled="isSaving" @click="openDefinition(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiPencilOutline" :size="18" />
											</template>
											{{ t('profile_fields', 'Edit field') }}
										</NcActionButton>
										<NcActionButton :disabled="isSaving" @click="toggleDefinitionActive(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="definition.active ? mdiEyeOffOutline : mdiEyeOutline" :size="18" />
											</template>
											{{ toggleDefinitionActiveLabel(definition) }}
										</NcActionButton>
										<NcActionButton :disabled="isSaving" @click="removeDefinitionByItem(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiDeleteOutline" :size="18" />
											</template>
											{{ t('profile_fields', 'Delete field') }}
										</NcActionButton>
									</NcActions>
									<NcButton
										class="profile-fields-admin__definition-handle"
										:data-testid="`profile-fields-admin-definition-handle-${definition.field_key}`"
										:aria-label="t('profile_fields', 'Drag to reorder')"
										variant="tertiary-no-background"
										:disabled="isSaving"
										tabindex="-1"
										@click.stop>
										<template #icon>
											<NcIconSvgWrapper :path="mdiDragVertical" :size="18" />
										</template>
									</NcButton>
								</template>
							</NcListItem>
						</li>
					</template>
				</Draggable>
			</aside>

			<component
				:is="editorShellComponent"
				v-bind="editorShellProps"
				class="profile-fields-admin__editor-shell"
				@update:open="updateEditorDialogOpen">
				<div
					class="profile-fields-admin__editor"
					:class="{ 'profile-fields-admin__editor--dialog': isCompactLayout }">
				<template v-if="isEditorVisible">
					<div class="profile-fields-admin__panel-header">
						<div class="profile-fields-admin__panel-header-copy">
							<h3>{{ isEditing ? t('profile_fields', 'Edit field') : t('profile_fields', 'Create field') }}</h3>
							<p>{{ editorDescription }}</p>
						</div>
						<div class="profile-fields-admin__editor-actions">
							<NcCheckboxRadioSwitch v-model="form.active" type="switch" class="profile-fields-admin__header-switch">
								{{ t('profile_fields', 'Active') }}
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<form id="profile-fields-admin-form" class="profile-fields-admin__form" data-testid="profile-fields-admin-form" @submit.prevent="persistDefinition">
					<section class="profile-fields-admin__form-section profile-fields-admin__form-section--identity">
						<div class="profile-fields-admin__section-heading">
							<h4>{{ t('profile_fields', 'Identity') }}</h4>
						</div>

						<div class="profile-fields-admin__grid profile-fields-admin__grid--identity">
							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-field-key">{{ t('profile_fields', 'Field key') }}</label>
								<NcInputField
									id="profile-fields-admin-field-key"
									v-model="form.fieldKey"
									:label="t('profile_fields', 'Field key')"
									label-outside
									:disabled="isEditing"
									:helper-text="t('profile_fields', 'Stable identifier used in APIs and integrations.')"
								/>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-label">{{ t('profile_fields', 'Label') }}</label>
								<NcInputField
									id="profile-fields-admin-label"
									v-model="form.label"
									:label="t('profile_fields', 'Label')"
									label-outside
									:helper-text="t('profile_fields', 'Visible name shown in settings and profile forms.')"
								/>
							</div>
						</div>
					</section>

					<section class="profile-fields-admin__form-section">
						<div class="profile-fields-admin__section-heading">
							<h4>{{ t('profile_fields', 'Rules') }}</h4>
						</div>

						<div class="profile-fields-admin__grid profile-fields-admin__grid--rules">
							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-edit-policy">{{ t('profile_fields', 'Edit policy') }}</label>
								<NcSelect
									input-id="profile-fields-admin-edit-policy"
									v-model="selectedEditPolicyOption"
									:input-label="t('profile_fields', 'Edit policy')"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="editPolicyOptions"
									label="label"
									:placeholder="t('profile_fields', 'Select who can edit')"
								/>
								<p class="profile-fields-admin__field-helper">{{ editPolicyDescription }}</p>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-visibility-policy">{{ t('profile_fields', 'Visibility') }}</label>
								<NcSelect
									input-id="profile-fields-admin-visibility-policy"
									v-model="selectedExposurePolicyOption"
									:input-label="t('profile_fields', 'Visibility')"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="exposurePolicyOptions"
									label="label"
									:placeholder="t('profile_fields', 'Select default visibility')"
								/>
								<p class="profile-fields-admin__field-helper">{{ exposurePolicyDescription }}</p>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-type">{{ t('profile_fields', 'Type') }}</label>
								<NcSelect
									data-testid="profile-fields-admin-type-select"
									input-id="profile-fields-admin-type"
									v-model="selectedTypeOption"
									:input-label="t('profile_fields', 'Type')"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="fieldTypeOptions"
									label="label"
									:placeholder="t('profile_fields', 'Select field type')"
								/>
							</div>
						</div>
					</section>

					<AdminSelectOptionsSection v-if="form.type === 'select' || form.type === 'multiselect'" v-model="form.options" :is-saving="isSaving" />

					<div v-if="!isCompactLayout" class="profile-fields-admin__submit-row">
						<NcButton type="submit" variant="primary" data-testid="profile-fields-admin-save" :disabled="isSaveDisabled">
							{{ saveActionLabel }}
						</NcButton>
						<NcButton v-if="isEditing" variant="error" data-testid="profile-fields-admin-delete" :disabled="isSaving" @click.prevent="removeDefinition">
							{{ t('profile_fields', 'Delete field') }}
						</NcButton>
					</div>
					</form>
				</template>

				<div v-else-if="!isCompactLayout" class="profile-fields-admin__empty-editor">
					<div class="profile-fields-admin__empty-editor-card">
						<h3>{{ editorEmptyState.title }}</h3>
						<p>{{ editorEmptyState.description }}</p>
						<div class="profile-fields-admin__empty-editor-actions">
							<NcButton variant="primary" @click="startCreatingField">
								{{ t('profile_fields', 'New field') }}
							</NcButton>
						</div>
					</div>
				</div>
			</div>
				<template v-if="isCompactLayout && isEditorVisible" #actions>
					<NcButton :disabled="isSaving" @click="closeEditor">
						{{ t('profile_fields', 'Cancel') }}
					</NcButton>
					<NcButton v-if="isEditing" variant="error" data-testid="profile-fields-admin-delete" :disabled="isSaving" @click.prevent="removeDefinition">
						{{ t('profile_fields', 'Delete field') }}
					</NcButton>
					<NcButton type="submit" form="profile-fields-admin-form" variant="primary" data-testid="profile-fields-admin-save" :disabled="isSaveDisabled">
						{{ saveActionLabel }}
					</NcButton>
				</template>
			</component>
		</div>

	</section>
</template>

<script setup lang="ts">
import { mdiDeleteOutline, mdiDragVertical, mdiEyeOffOutline, mdiEyeOutline, mdiPencilOutline } from '@mdi/js'
import { n, t } from '@nextcloud/l10n'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import Draggable from 'vuedraggable'
import AdminSupportBanner from '../components/AdminSupportBanner.vue'
import AdminSelectOptionsSection from '../components/admin/AdminSelectOptionsSection.vue'
import { NcActionButton, NcActions, NcButton, NcCheckboxRadioSwitch, NcChip, NcEmptyContent, NcIconSvgWrapper, NcInputField, NcListItem, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { createDefinition, deleteDefinition, listDefinitions, updateDefinition } from '../api'
import type { FieldDefinition, FieldEditPolicy, FieldExposurePolicy, FieldType } from '../types'
import { createEditableSelectOptions, extractEditableSelectOptionValues } from '../utils/selectFieldOptions.js'

const fieldTypeOptions: Array<{ value: FieldType, label: string }> = [
	{ value: 'text', label: t('profile_fields', 'Text') },
	{ value: 'number', label: t('profile_fields', 'Number') },
	{ value: 'date', label: t('profile_fields', 'Date') },
	{ value: 'select', label: t('profile_fields', 'Select') },
	{ value: 'multiselect', label: t('profile_fields', 'Multiselect') },
]

const editPolicyOptions: Array<{ value: FieldEditPolicy, label: string }> = [
	{ value: 'users', label: t('profile_fields', 'Users can edit') },
	{ value: 'admins', label: t('profile_fields', 'Admins only') },
]

const exposurePolicyOptions: Array<{ value: FieldExposurePolicy, label: string }> = [
	{ value: 'hidden', label: t('profile_fields', 'Hidden from users') },
	{ value: 'private', label: t('profile_fields', 'Visible to users, private by default') },
	{ value: 'users', label: t('profile_fields', 'Visible to users, shared with users by default') },
	{ value: 'public', label: t('profile_fields', 'Visible to everyone by default') },
]

const definitions = ref<FieldDefinition[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const selectedId = ref<number | null>(null)
const justSavedId = ref<number | null>(null)
let justSavedTimeout: ReturnType<typeof setTimeout> | null = null
const successMessage = ref('')
let successMessageTimeout: ReturnType<typeof setTimeout> | null = null

const setSuccessMessage = (message: string) => {
	if (successMessageTimeout !== null) {
		clearTimeout(successMessageTimeout)
	}
	successMessage.value = message
	successMessageTimeout = setTimeout(() => {
		successMessage.value = ''
		successMessageTimeout = null
	}, 4000)
}

const markJustSaved = (id: number) => {
	if (justSavedTimeout !== null) {
		clearTimeout(justSavedTimeout)
	}
	justSavedId.value = id
	justSavedTimeout = setTimeout(() => {
		justSavedId.value = null
		justSavedTimeout = null
	}, 2000)
}
const isCreatingNew = ref(false)
const isCompactLayout = ref(false)
let nextOptionId = 0
let compactLayoutMediaQuery: MediaQueryList | null = null

const createOptionId = () => `option-${nextOptionId++}`

const form = reactive({
	fieldKey: '',
	label: '',
	type: 'text' as FieldType,
	editPolicy: 'users' as FieldEditPolicy,
	exposurePolicy: 'private' as FieldExposurePolicy,
	sortOrder: 0,
	active: true,
	options: createEditableSelectOptions([], createOptionId),
})

const editPolicyDescription = computed(() => form.editPolicy === 'admins'
	? t('profile_fields', 'Only administrators can create or update values for this field.')
	: t('profile_fields', 'Users can update their own value in personal settings.'))
const exposurePolicyDescription = computed(() => {
	switch (form.exposurePolicy) {
	case 'hidden':
		return t('profile_fields', 'The field is hidden from personal settings and global search.')
	case 'users':
		return t('profile_fields', 'Shown in personal settings. New values are shared with signed-in users.')
	case 'public':
		return t('profile_fields', 'Shown in personal settings. New values are visible to everyone.')
	default:
		return t('profile_fields', 'Shown in personal settings. New values start as private.')
	}
})
const editorDescription = computed(() => isEditing.value
	? t('profile_fields', 'Update the selected field and its rules.')
	: t('profile_fields', 'Set up a new field and its rules.'))
const selectedDefinition = computed(() => definitions.value.find((definition: FieldDefinition) => definition.id === selectedId.value) ?? null)
const sortedDefinitions = computed(() => [...definitions.value].sort((left, right) => left.sort_order - right.sort_order || left.id - right.id))
const isEditing = computed(() => selectedDefinition.value !== null)
const isEditorVisible = computed(() => isCreatingNew.value || isEditing.value)
const editorShellComponent = computed(() => isCompactLayout.value ? NcDialog : 'div')
const editorShellProps = computed(() => isCompactLayout.value
	? {
		open: isEditorVisible.value,
		name: isEditing.value ? t('profile_fields', 'Edit field') : t('profile_fields', 'Create field'),
		size: 'large',
		contentClasses: 'profile-fields-admin__editor-dialog',
	}
	: {})
const editorEmptyState = computed(() => sortedDefinitions.value.length === 0
	? {
		title: t('profile_fields', 'No fields configured'),
		description: t('profile_fields', 'Create your first field to start building the catalog.'),
	}
	: {
		title: t('profile_fields', 'No field selected'),
		description: t('profile_fields', 'Select a field from the list, or create a new one.'),
	})
const configuredFieldsCountLabel = computed(() => n('profile_fields', 'field configured', 'fields configured', definitions.value.length, { count: definitions.value.length }))
const saveActionLabel = computed(() => isSaving.value ? t('profile_fields', 'Saving changes...') : (isEditing.value ? t('profile_fields', 'Save changes') : t('profile_fields', 'Create field')))
const editFieldAriaLabel = (label: string) => t('profile_fields', 'Edit field {label}', { label })
const actionsForLabel = (label: string) => t('profile_fields', 'Actions for {label}', { label })
const toggleDefinitionActiveLabel = (definition: FieldDefinition) => definition.active
	? t('profile_fields', 'Disable field')
	: t('profile_fields', 'Enable field')

const buildFormState = () => ({
	fieldKey: form.fieldKey,
	label: form.label,
	type: form.type,
	editPolicy: form.editPolicy,
	exposurePolicy: form.exposurePolicy,
	sortOrder: Number(form.sortOrder),
	active: form.active,
	options: (form.type === 'select' || form.type === 'multiselect') ? extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '') : [],
})

const buildDefinitionState = (definition: FieldDefinition | null) => {
	if (definition === null) {
		return {
			fieldKey: '',
			label: '',
			type: 'text' as FieldType,
			editPolicy: 'users' as FieldEditPolicy,
			exposurePolicy: 'private' as FieldExposurePolicy,
			sortOrder: definitions.value.length,
			active: true,
			options: [],
		}
	}

	return {
		fieldKey: definition.field_key,
		label: definition.label,
		type: definition.type,
		editPolicy: definition.edit_policy,
		exposurePolicy: definition.exposure_policy,
		sortOrder: definition.sort_order,
		active: definition.active,
		options: (definition.type === 'select' || definition.type === 'multiselect') ? (definition.options ?? []) : [],
	}
}

const isFormDirty = computed(() => JSON.stringify(buildFormState()) !== JSON.stringify(buildDefinitionState(selectedDefinition.value)))

const hasDuplicateOptions = computed(() => {
	const seen = new Set<string>()
	for (const option of form.options) {
		const normalized = option.value.trim().toLocaleLowerCase()
		if (normalized === '') {
			continue
		}
		if (seen.has(normalized)) {
			return true
		}
		seen.add(normalized)
	}
	return false
})
const normalizedOptionCount = computed(() => extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '').length)
const hasRequiredFields = computed(() => {
	if (form.fieldKey.trim() === '' || form.label.trim() === '') {
		return false
	}

	if ((form.type === 'select' || form.type === 'multiselect') && normalizedOptionCount.value === 0) {
		return false
	}

	return true
})
const isSaveDisabled = computed(() => isSaving.value || !isFormDirty.value || hasDuplicateOptions.value || !hasRequiredFields.value)

const selectedTypeOption = computed({
	get: () => fieldTypeOptions.find((option) => option.value === form.type) ?? fieldTypeOptions[0],
	set: (option: { value: FieldType, label: string } | null) => {
		if (option !== null) {
			form.type = option.value
		}
	},
})
const selectedEditPolicyOption = computed({
	get: () => editPolicyOptions.find((option) => option.value === form.editPolicy) ?? editPolicyOptions[0],
	set: (option: { value: FieldEditPolicy, label: string } | null) => {
		if (option !== null) {
			form.editPolicy = option.value
		}
	},
})
const selectedExposurePolicyOption = computed({
	get: () => exposurePolicyOptions.find((option) => option.value === form.exposurePolicy) ?? exposurePolicyOptions[0],
	set: (option: { value: FieldExposurePolicy, label: string } | null) => {
		if (option !== null) {
			form.exposurePolicy = option.value
		}
	},
})

const resetForm = () => {
	selectedId.value = null
	form.fieldKey = ''
	form.label = ''
	form.type = 'text'
	form.editPolicy = 'users'
	form.exposurePolicy = 'private'
	form.sortOrder = definitions.value.length
	form.active = true
	form.options = createEditableSelectOptions([], createOptionId)
}

const closeEditor = () => {
	isCreatingNew.value = false
	resetForm()
}

const startCreatingField = () => {
	isCreatingNew.value = true
	resetForm()
}

const openDefinition = (definition: FieldDefinition) => {
	populateForm(definition)
}

const handleDefinitionClick = (definition: FieldDefinition) => {
	if (isSaving.value) {
		return
	}

	openDefinition(definition)
}

const updateEditorDialogOpen = (open: boolean) => {
	if (!open && isCompactLayout.value) {
		closeEditor()
	}
}

const updateCompactLayout = (matches: boolean) => {
	isCompactLayout.value = matches
}

const handleCompactLayoutChange = (event: MediaQueryListEvent) => {
	updateCompactLayout(event.matches)
}

const buildDefinitionUpdatePayload = (definition: FieldDefinition, sortOrder: number) => ({
	label: definition.label,
	type: definition.type,
	editPolicy: definition.edit_policy,
	exposurePolicy: definition.exposure_policy,
	sortOrder,
	active: definition.active,
	...((definition.type === 'select' || definition.type === 'multiselect') ? { options: definition.options ?? [] } : {}),
})

const replaceDefinitionInState = (definition: FieldDefinition) => {
	const existingIndex = definitions.value.findIndex((candidate: FieldDefinition) => candidate.id === definition.id)
	if (existingIndex === -1) {
		definitions.value = [...definitions.value, definition]
		return
	}

	definitions.value = definitions.value.map((candidate: FieldDefinition) => candidate.id === definition.id ? definition : candidate)
}

const removeDefinitionFromState = (definitionId: number) => {
	definitions.value = definitions.value.filter((definition: FieldDefinition) => definition.id !== definitionId)
}

const populateForm = (definition: FieldDefinition) => {
	isCreatingNew.value = false
	selectedId.value = definition.id
	form.fieldKey = definition.field_key
	form.label = definition.label
	form.type = definition.type
	form.editPolicy = definition.edit_policy
	form.exposurePolicy = definition.exposure_policy
	form.sortOrder = definition.sort_order
	form.active = definition.active
	form.options = (definition.type === 'select' || definition.type === 'multiselect')
		? createEditableSelectOptions(definition.options ?? [], createOptionId)
		: createEditableSelectOptions([], createOptionId)
}

const loadDefinitions = async() => {
	isLoading.value = true
	errorMessage.value = ''
	try {
		definitions.value = await listDefinitions()
		if (selectedDefinition.value !== null) {
			populateForm(selectedDefinition.value)
		} else if (isCreatingNew.value) {
			resetForm()
		}
	} catch (error) {
		errorMessage.value = error instanceof Error ? error.message : t('profile_fields', 'Could not load field definitions.')
	} finally {
		isLoading.value = false
	}
}

const persistDefinition = async() => {
	if (!isFormDirty.value) {
		return
	}

	isSaving.value = true
	errorMessage.value = ''

	const payload = {
		fieldKey: form.fieldKey,
		label: form.label,
		type: form.type,
		editPolicy: form.editPolicy,
		exposurePolicy: form.exposurePolicy,
		sortOrder: Number(form.sortOrder),
		active: form.active,
		...((form.type === 'select' || form.type === 'multiselect')
			? { options: extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '') }
			: {}),
	}

	try {
		if (selectedDefinition.value === null) {
			const created = await createDefinition(payload)
			replaceDefinitionInState(created)
			selectedId.value = created.id
			populateForm(created)
			markJustSaved(created.id)
			setSuccessMessage(t('profile_fields', 'Field created successfully.'))
		} else {
			const updated = await updateDefinition(selectedDefinition.value.id, {
				label: payload.label,
				type: payload.type,
				editPolicy: payload.editPolicy,
				exposurePolicy: payload.exposurePolicy,
				sortOrder: payload.sortOrder,
				active: payload.active,
				...((payload.type === 'select' || payload.type === 'multiselect') ? { options: payload.options } : {}),
			})
			replaceDefinitionInState(updated)
			populateForm(updated)
			markJustSaved(updated.id)
			setSuccessMessage(t('profile_fields', 'Field updated successfully.'))
		}
		if (isCompactLayout.value) {
			closeEditor()
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? t('profile_fields', 'Could not save this field. Please try again.')
	} finally {
		isSaving.value = false
	}
}

const removeDefinition = async() => {
	if (selectedDefinition.value === null) {
		return
	}

	isSaving.value = true
	errorMessage.value = ''
	try {
		await deleteDefinition(selectedDefinition.value.id)
		removeDefinitionFromState(selectedDefinition.value.id)
		isCreatingNew.value = false
		resetForm()
		setSuccessMessage(t('profile_fields', 'Field deleted successfully.'))
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? t('profile_fields', 'Could not delete this field. Please try again.')
	} finally {
		isSaving.value = false
	}
}

const reorderDefinitions = async(event: { moved?: { oldIndex: number, newIndex: number } }) => {
	if (event.moved === undefined || event.moved.oldIndex === event.moved.newIndex) {
		return
	}

	const reordered = [...sortedDefinitions.value]
	const [movedDefinition] = reordered.splice(event.moved.oldIndex, 1)
	if (movedDefinition === undefined) {
		return
	}
	reordered.splice(event.moved.newIndex, 0, movedDefinition)

	const updates = reordered
		.map((definition, index) => ({ definition, sortOrder: index }))
		.filter(({ definition, sortOrder }) => definition.sort_order !== sortOrder)

	if (updates.length === 0) {
		return
	}

	isSaving.value = true
	errorMessage.value = ''
	const previousDefinitions = definitions.value

	try {
		definitions.value = reordered.map((definition, index) => ({
			...definition,
			sort_order: index,
		}))
		const persistedDefinitions = await Promise.all(updates.map(({ definition, sortOrder }) => updateDefinition(
			definition.id,
			buildDefinitionUpdatePayload(definition, sortOrder),
		)))
		persistedDefinitions.forEach((definition) => replaceDefinitionInState(definition))

		if (selectedDefinition.value !== null) {
			const currentDefinition = reordered.find((definition) => definition.id === selectedDefinition.value?.id)
			if (currentDefinition !== undefined) {
				form.sortOrder = currentDefinition.sort_order
			}
		}
	} catch (error: any) {
		definitions.value = previousDefinitions
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? t('profile_fields', 'Could not save the new order. Please try again.')
	} finally {
		isSaving.value = false
	}
}

const toggleDefinitionActive = async(definition: FieldDefinition) => {
	isSaving.value = true
	errorMessage.value = ''
	try {
		const updated = await updateDefinition(definition.id, {
			...buildDefinitionUpdatePayload(definition, definition.sort_order),
			active: !definition.active,
		})
		replaceDefinitionInState(updated)
		markJustSaved(updated.id)
		if (selectedDefinition.value?.id === definition.id) {
			populateForm(updated)
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? t('profile_fields', 'Could not update this field. Please try again.')
	} finally {
		isSaving.value = false
	}
}

const removeDefinitionByItem = async(definition: FieldDefinition) => {
	isSaving.value = true
	errorMessage.value = ''
	try {
		await deleteDefinition(definition.id)
		removeDefinitionFromState(definition.id)
		if (selectedDefinition.value?.id === definition.id) {
			isCreatingNew.value = false
			resetForm()
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? t('profile_fields', 'Could not delete this field. Please try again.')
	} finally {
		isSaving.value = false
	}
}

watch(() => form.type, (newType: FieldType) => {
	if (newType === 'select' || newType === 'multiselect') {
		if (form.options.length === 0) {
			form.options = createEditableSelectOptions([''], createOptionId)
		}
	} else {
		form.options = createEditableSelectOptions([], createOptionId)
	}
})

onMounted(() => {
	loadDefinitions()
	compactLayoutMediaQuery = window.matchMedia('(max-width: 1024px)')
	updateCompactLayout(compactLayoutMediaQuery.matches)
	compactLayoutMediaQuery.addEventListener('change', handleCompactLayoutChange)
})

onBeforeUnmount(() => {
	compactLayoutMediaQuery?.removeEventListener('change', handleCompactLayoutChange)
	if (justSavedTimeout !== null) {
		clearTimeout(justSavedTimeout)
	}
	if (successMessageTimeout !== null) {
		clearTimeout(successMessageTimeout)
	}
})
</script>

<style scoped lang="scss">
.profile-fields-admin {
	position: relative;
	display: grid;
	gap: 18px;
	color: var(--color-main-text);

	&__hero {
		display: flex;
		justify-content: space-between;
		gap: 24px;
		padding: 20px 22px;
		border-radius: 20px;
		background:
			radial-gradient(circle at top right, color-mix(in srgb, var(--color-primary-element) 14%, transparent), transparent 34%),
			linear-gradient(135deg, color-mix(in srgb, var(--color-background-darker) 76%, var(--color-main-background) 24%), color-mix(in srgb, var(--color-main-background) 94%, var(--color-primary-element) 6%));
		border: 1px solid color-mix(in srgb, var(--color-primary-element) 24%, var(--color-border-default) 76%);
		box-shadow: 0 12px 32px rgba(15, 23, 42, 0.1);

	}

	&__hero-copy {
		padding-inline-start: clamp(18px, 2.4vw, 28px);

		h2 {
			margin: 0 0 8px;
			font-size: 24px;
		}

		p {
			margin: 0;
			max-width: 58ch;
			font-size: 14px;
			color: color-mix(in srgb, var(--color-main-text) 82%, transparent);
		}
	}

	&__hero-meta {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		justify-content: center;
		min-width: 120px;
		padding: 12px 14px;
		border-radius: 14px;
		background: color-mix(in srgb, var(--color-main-background) 90%, transparent);

		strong {
			font-size: 32px;
			line-height: 1;
		}

		span {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__layout {
		display: grid;
		grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
		gap: 20px;
	}

	&__field-helper {
		margin: 8px 0 0;
		color: var(--color-text-maxcontrast);
	}

	&__list-panel,
	&__editor {
		min-width: 0;
		padding: 20px;
		border-radius: 20px;
		background: color-mix(in srgb, var(--color-main-background) 96%, var(--color-background-dark) 4%);
		border: 1px solid color-mix(in srgb, var(--color-border-default) 84%, transparent);
		box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
	}

	&__editor {
		display: grid;
		justify-content: center;

		> * {
			width: min(100%, 980px);
		}

		&--dialog {
			padding: 6px 0 12px;
			border: 0;
			background: transparent;
			box-shadow: none;

			> * {
				width: 100%;
			}
		}
	}

	&__panel-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 12px;
		margin-bottom: 16px;

		> :deep(.button-vue) {
			flex: 0 0 auto;
			width: auto;
			min-width: max-content;
		}
	}

	&__panel-header-copy {
		flex: 1 1 auto;
		min-width: 0;

		h3 {
			margin: 0;
		}

		p {
			margin: 6px 0 0;
			color: var(--color-text-maxcontrast);
		}

	}

	&__editor-actions {
		display: flex;
		gap: 8px;
		align-items: center;
		flex: 0 0 auto;
	}

	&__header-switch {
		padding: 6px 10px;
		border-radius: 999px;
		border: 1px solid color-mix(in srgb, var(--color-border-default) 84%, transparent);
		background: color-mix(in srgb, var(--color-main-background) 98%, var(--color-background-hover) 2%);

		:deep(.checkbox-content__description) {
			display: none;
		}

		:deep(.checkbox-content__text) {
			font-weight: 600;
		}
	}

	&__list-type-tag {
		align-self: start;
		padding: 4px 8px;
		border-radius: 999px;
		background: color-mix(in srgb, var(--color-primary-element) 12%, transparent);
		font-size: 12px;
		font-weight: 700;
		text-transform: capitalize;
	}

	&__list {
		list-style: none;
		padding: 0;
		margin: 0;
		display: grid;
		gap: 10px;
	}

	&__list-row {
		display: block;

		&.is-disabled {
			opacity: 0.72;
		}

		&--ghost {
			:deep(.list-item) {
				opacity: 0.5;
			}
		}

		&--chosen {
			:deep(.list-item) {
				box-shadow: 0 8px 18px color-mix(in srgb, var(--color-box-shadow) 55%, transparent);
			}
		}
	}

	&__list-item {
		min-width: 0;

		&.is-selected {
			:deep(.list-item) {
				box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 20%, transparent);
			}
		}

		&.is-just-saved {
			:deep(.list-item) {
				box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-success) 22%, transparent);
				transition: box-shadow 0.3s ease;
			}
		}

		:deep(.list-item) {
			min-height: 72px;
			transition: box-shadow 0.2s ease, background-color 0.2s ease;
		}

		:deep(.list-item-content__main) {
			min-width: 0;
		}

		:deep(.list-item-content__name),
		:deep(.list-item-content__subname) {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		:deep(.list-item-content__subname) {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__definition-status {
		flex: 0 0 auto;
	}

	&__definition-actions {
		flex: 0 0 auto;
	}

	&__definition-handle {
		cursor: grab;
		user-select: none;
		opacity: 0.72;

		&:active {
			cursor: grabbing;
		}

		&:hover,
		&:focus-visible {
			opacity: 1;
		}
	}

	&__list-item-subname {
		display: block;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__form {
		display: grid;
		gap: 16px;
		justify-items: stretch;

		> * {
			width: 100%;
		}
	}

	&__form-section {
		display: grid;
		gap: 16px;
		padding: 18px;
		border-radius: 18px;
		background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-hover) 8%);
		border: 1px solid color-mix(in srgb, var(--color-border-default) 86%, transparent);
	}

	&__section-heading {
		h4 {
			margin: 0;
			font-size: 16px;
		}
	}

	&__form label {
		display: grid;
		gap: 8px;
		font-weight: 600;
	}

	&__field {
		display: grid;
		gap: 8px;
		min-width: 0;

		> label {
			font-size: 14px;
		}

		:deep(.input-field__main-wrapper),
		:deep(.multiselect) {
			width: 100%;
			min-width: 0;
		}
	}

	&__grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 420px));
		gap: 14px;
		align-items: start;
		justify-content: start;

		&--identity {
			grid-template-columns: repeat(auto-fit, minmax(min(100%, 320px), 420px));
		}

		&--rules {
			grid-template-columns: repeat(2, minmax(min(100%, 280px), 420px));
			max-width: 980px;
			column-gap: 18px;
			row-gap: 18px;
		}
	}

	&__toggle-card {
		padding: 14px;
		border-radius: 16px;
		border: 1px solid color-mix(in srgb, var(--color-border-default) 84%, transparent);
		background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-hover) 8%);
		min-height: 100%;

		:deep(.checkbox-content) {
			align-items: flex-start;
			width: 100%;
		}

		:deep(.checkbox-content__wrapper) {
			display: grid;
			gap: 4px;
		}

		:deep(.checkbox-content__text) {
			font-size: 14px;
			line-height: 1.3;
			font-weight: 600;
		}

		:deep(.checkbox-content__description) {
			color: var(--color-text-maxcontrast);
			font-size: 12px;
			line-height: 1.4;
			display: block;
			min-block-size: calc(1.4em * 3);
		}
	}

	&__submit-row {
		display: flex;
		gap: 10px;
		justify-content: flex-end;
		padding-top: 4px;
		width: 100%;
	}


	&__empty-editor {
		display: grid;
		place-items: center;
		min-height: 320px;
		padding: 24px 8px;
	}

	&__empty-editor-card {
		display: grid;
		justify-items: center;
		gap: 12px;
		max-width: 440px;
		text-align: center;

		h3 {
			margin: 0;
			font-size: 18px;
		}

		p {
			margin: 0;
			color: var(--color-text-maxcontrast);
		}
	}

	&__empty-editor-actions {
		display: flex;
		justify-content: center;
		padding-top: 4px;
	}

	&__success-announcement {
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

	&__loading {
		display: flex;
		justify-content: center;
		padding: 40px 0;
	}

	:deep(.profile-fields-admin__editor-dialog) {
		width: min(100vw - 20px, 880px);
		max-width: 100%;
		padding-top: 8px;
		padding-bottom: 8px;
	}

	:deep(.profile-fields-admin__editor-dialog ~ .modal-container__buttons),
	:deep(.profile-fields-admin__editor-dialog + .modal-container__buttons) {
		padding-top: 8px;
	}
}

@media (max-width: 1360px) {
	.profile-fields-admin {
		&__layout {
			grid-template-columns: 1fr;
		}

		&__list-panel,
		&__editor {
			width: 100%;
		}

		&__panel-header {
			align-items: flex-start;
			flex-direction: column;
		}

		&__editor-actions {
			flex-wrap: wrap;
			justify-content: flex-start;
		}

	}
}

@media (max-width: 1024px) {
	.profile-fields-admin {
		&__layout,
		&__grid--identity,
		&__grid--rules {
			grid-template-columns: 1fr;
		}

		&__hero {
			flex-direction: column;
		}

		&__hero-meta {
			justify-items: start;
			width: 100%;
		}

		&__submit-row,
		&__editor-actions {
			flex-wrap: wrap;
		}

		&__panel-header {
			align-items: flex-start;
			flex-direction: column;
		}
	}
}

@media (max-width: 720px) {
	.profile-fields-admin {
		&__hero {
			padding: 18px;
		}

		&__hero-copy {
			padding-inline-start: 28px;
		}
	}
}
</style>
