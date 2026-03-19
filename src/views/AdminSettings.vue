<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors

SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section class="profile-fields-admin" data-testid="profile-fields-admin">
		<header class="profile-fields-admin__hero">
			<div>
				<h2>Field catalog administration</h2>
				<p>
					Create the global field catalog, choose who can edit each field, and define how exposed new values are by default.
				</p>
			</div>
			<div class="profile-fields-admin__hero-meta">
				<strong>{{ definitions.length }}</strong>
				<span>registered fields</span>
			</div>
		</header>

		<NcNoteCard v-if="errorMessage" type="error" data-testid="profile-fields-admin-error">
			{{ errorMessage }}
		</NcNoteCard>

		<div v-if="isLoading" class="profile-fields-admin__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-else class="profile-fields-admin__layout">
			<aside class="profile-fields-admin__list-panel">
				<div class="profile-fields-admin__panel-header">
					<div>
						<h3>Defined fields</h3>
						<p>Pick a field to edit it or create a new one.</p>
					</div>
					<NcButton variant="secondary" data-testid="profile-fields-admin-new-field" @click="startCreatingField">
						New field
					</NcButton>
				</div>

				<NcEmptyContent v-if="sortedDefinitions.length === 0" name="No fields yet" description="The catalog is empty. Create the first field to make it available to users." />

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
								:link-aria-label="`Edit field ${definition.label}`"
								@click="handleDefinitionClick(definition)">
								<template #subname>
									<span class="profile-fields-admin__list-item-subname">{{ definition.field_key }}</span>
								</template>
								<template #extra-actions>
									<NcChip
										class="profile-fields-admin__definition-status"
										:text="definition.active ? 'Active' : 'Inactive'"
										:variant="definition.active ? 'success' : 'secondary'"
										:no-close="true" />
									<NcActions
										class="profile-fields-admin__definition-actions"
										:aria-label="`Actions for ${definition.label}`">
										<NcActionButton :disabled="isSaving" @click="openDefinition(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiPencilOutline" :size="18" />
											</template>
											Edit field
										</NcActionButton>
										<NcActionButton :disabled="isSaving" @click="toggleDefinitionActive(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="definition.active ? mdiEyeOffOutline : mdiEyeOutline" :size="18" />
											</template>
											{{ definition.active ? 'Deactivate field' : 'Activate field' }}
										</NcActionButton>
										<NcActionButton :disabled="isSaving" @click="removeDefinitionByItem(definition)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiDeleteOutline" :size="18" />
											</template>
											Delete field
										</NcActionButton>
									</NcActions>
									<NcButton
										class="profile-fields-admin__definition-handle"
										:data-testid="`profile-fields-admin-definition-handle-${definition.field_key}`"
										aria-label="Drag to reorder field"
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
						<div>
							<h3>{{ isEditing ? 'Edit field' : 'Create field' }}</h3>
							<p>{{ editorDescription }}</p>
						</div>
						<div class="profile-fields-admin__editor-actions">
							<NcCheckboxRadioSwitch v-model="form.active" type="switch" class="profile-fields-admin__header-switch">
								Active
							</NcCheckboxRadioSwitch>
						</div>
					</div>

					<form id="profile-fields-admin-form" class="profile-fields-admin__form" data-testid="profile-fields-admin-form" @submit.prevent="persistDefinition">
					<section class="profile-fields-admin__form-section profile-fields-admin__form-section--identity">
						<div class="profile-fields-admin__section-heading">
							<h4>Identity</h4>
						</div>

						<div class="profile-fields-admin__grid profile-fields-admin__grid--identity">
							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-field-key">Field key</label>
								<NcInputField
									id="profile-fields-admin-field-key"
									v-model="form.fieldKey"
									label="Field key"
									label-outside
									:disabled="isEditing"
									helper-text="Used as the stable API identifier for this field."
								/>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-label">Label</label>
								<NcInputField
									id="profile-fields-admin-label"
									v-model="form.label"
									label="Label"
									label-outside
									helper-text="Displayed to admins and users as the field name."
								/>
							</div>
						</div>
					</section>

					<section class="profile-fields-admin__form-section">
						<div class="profile-fields-admin__section-heading">
							<h4>Rules</h4>
						</div>

						<div class="profile-fields-admin__grid profile-fields-admin__grid--rules">
							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-edit-policy">Edit policy</label>
								<NcSelect
									input-id="profile-fields-admin-edit-policy"
									v-model="selectedEditPolicyOption"
									input-label="Edit policy"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="editPolicyOptions"
									label="label"
									placeholder="Choose edit policy"
								/>
								<p class="profile-fields-admin__field-helper">{{ editPolicyDescription }}</p>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-visibility-policy">Visibility</label>
								<NcSelect
									input-id="profile-fields-admin-visibility-policy"
									v-model="selectedExposurePolicyOption"
									input-label="Visibility"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="exposurePolicyOptions"
									label="label"
									placeholder="Choose visibility"
								/>
								<p class="profile-fields-admin__field-helper">{{ exposurePolicyDescription }}</p>
							</div>

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-type">Type</label>
								<NcSelect
									data-testid="profile-fields-admin-type-select"
									input-id="profile-fields-admin-type"
									v-model="selectedTypeOption"
									input-label="Type"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="fieldTypeOptions"
									label="label"
									placeholder="Choose a field type"
								/>
							</div>
						</div>
					</section>

					<section v-if="form.type === 'select'" class="profile-fields-admin__form-section">
						<div class="profile-fields-admin__section-heading profile-fields-admin__section-heading--split">
							<div>
								<h4>Options</h4>
							</div>
							<div class="profile-fields-admin__options-meta">
								<strong>{{ normalizedOptionCount }}</strong>
								<span>{{ normalizedOptionCount === 1 ? 'option' : 'options' }}</span>
							</div>
						</div>

						<Draggable
							v-model="form.options"
							class="profile-fields-admin__options-editor"
							data-testid="profile-fields-admin-options-editor"
							item-key="id"
							handle=".profile-fields-admin__option-handle"
							ghost-class="profile-fields-admin__option-row--ghost"
							chosen-class="profile-fields-admin__option-row--chosen"
							:animation="180"
							:disabled="isSaving">
							<template #item="{ element, index }">
								<div class="profile-fields-admin__option-row" :data-testid="`profile-fields-admin-option-row-${index}`">
									<div class="profile-fields-admin__option-leading">
										<NcActions
											v-if="hasOptionValue(index)"
											class="profile-fields-admin__option-handle"
											:data-testid="`profile-fields-admin-option-handle-${index}`"
											variant="tertiary-no-background"
											size="small"
											:aria-label="`Reorder option ${element.value}`">
											<template #icon>
												<NcIconSvgWrapper :path="mdiDragVertical" :size="18" />
											</template>
											<NcActionButton :disabled="!canMoveOptionUp(index) || isSaving" @click="moveOption(index, -1)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiArrowUp" :size="18" />
												</template>
												Move up
											</NcActionButton>
											<NcActionButton :disabled="!canMoveOptionDown(index) || isSaving" @click="moveOption(index, 1)">
												<template #icon>
													<NcIconSvgWrapper :path="mdiArrowDown" :size="18" />
												</template>
												Move down
											</NcActionButton>
										</NcActions>
										<div v-else class="profile-fields-admin__option-handle-spacer" aria-hidden="true" />
									</div>
									<NcInputField
										:model-value="element.value"
										label="Option value"
										label-outside
										:placeholder="`Option ${index + 1}`"
										:error="isOptionDuplicate(index)"
										:helper-text="isOptionDuplicate(index) ? 'Duplicate option' : ''"
										@update:model-value="updateOption(index, $event)"
										@keydown.enter.prevent="addOptionFromEnter(index, $event)"
										@keydown.backspace="removeEmptyOptionFromKeyboard(index, $event)"
										@keydown.delete="removeEmptyOptionFromKeyboard(index, $event)"
										@blur="cleanupEmptyOptionOnBlur(element.id)"
									/>
									<div class="profile-fields-admin__option-actions">
										<NcButton
											variant="tertiary-no-background"
											:aria-label="`Remove option ${element.value || String(index + 1)}`"
											@click.prevent="removeOption(index)">
											<template #icon>
												<NcIconSvgWrapper :path="mdiClose" :size="20" />
											</template>
										</NcButton>
									</div>
								</div>
							</template>
						</Draggable>

						<div class="profile-fields-admin__option-toolbar">
							<NcButton variant="secondary" data-testid="profile-fields-admin-add-option" @click.prevent="addOption">
								Add option
							</NcButton>
							<NcButton variant="secondary" data-testid="profile-fields-admin-add-multiple-options" @click.prevent="openBulkOptionsDialog">
								Add multiple options
							</NcButton>
						</div>
					</section>

					<div v-if="!isCompactLayout" class="profile-fields-admin__submit-row">
						<NcButton type="submit" variant="primary" data-testid="profile-fields-admin-save" :disabled="isSaveDisabled">
							{{ isSaving ? 'Saving...' : (isEditing ? 'Save changes' : 'Create field') }}
						</NcButton>
						<NcButton v-if="isEditing" variant="error" data-testid="profile-fields-admin-delete" :disabled="isSaving" @click.prevent="removeDefinition">
							Delete field
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
								New field
							</NcButton>
						</div>
					</div>
				</div>
			</div>
				<template v-if="isCompactLayout && isEditorVisible" #actions>
					<NcButton :disabled="isSaving" @click="closeEditor">
						Cancel
					</NcButton>
					<NcButton v-if="isEditing" variant="error" data-testid="profile-fields-admin-delete" :disabled="isSaving" @click.prevent="removeDefinition">
						Delete field
					</NcButton>
					<NcButton type="submit" form="profile-fields-admin-form" variant="primary" data-testid="profile-fields-admin-save" :disabled="isSaveDisabled">
						{{ isSaving ? 'Saving...' : (isEditing ? 'Save changes' : 'Create field') }}
					</NcButton>
				</template>
			</component>
		</div>

		<NcDialog
			:open="isBulkOptionsDialogOpen"
			name="Add multiple options"
			content-classes="profile-fields-admin__bulk-options-dialog"
			size="normal"
			@update:open="updateBulkOptionsDialogOpen">
			<div class="profile-fields-admin__bulk-options-content">
				<NcTextArea
					data-testid="profile-fields-admin-bulk-options-input"
					:model-value="bulkOptionInput"
					label="Add multiple options (one per line)"
					placeholder="Add multiple options (one per line)"
					resize="vertical"
					rows="10"
					@update:model-value="bulkOptionInput = $event" />
				<p class="profile-fields-admin__bulk-options-summary">
					{{ bulkOptionValues.length === 1 ? '1 option ready to add.' : `${bulkOptionValues.length} options ready to add.` }}
				</p>
			</div>

			<template #actions>
				<NcButton @click="closeBulkOptionsDialog">
					Cancel
				</NcButton>
				<NcButton
					variant="primary"
					data-testid="profile-fields-admin-bulk-options-submit"
					:disabled="bulkOptionValues.length === 0"
					@click="applyBulkOptions">
					Add options
				</NcButton>
			</template>
		</NcDialog>
	</section>
</template>

<script setup lang="ts">
import { mdiArrowDown, mdiArrowUp, mdiClose, mdiDeleteOutline, mdiDragVertical, mdiEyeOffOutline, mdiEyeOutline, mdiPencilOutline } from '@mdi/js'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import Draggable from 'vuedraggable'
import { NcActionButton, NcActions, NcButton, NcCheckboxRadioSwitch, NcChip, NcEmptyContent, NcIconSvgWrapper, NcInputField, NcListItem, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { createDefinition, deleteDefinition, listDefinitions, updateDefinition } from '../api'
import type { FieldDefinition, FieldEditPolicy, FieldExposurePolicy, FieldType } from '../types'
import { createEditableSelectOptions, extractEditableSelectOptionValues, moveEditableSelectOption, normalizeEditableSelectOptionValue, parseEditableSelectOptionValues } from '../utils/selectFieldOptions.js'
import type { EditableSelectOption } from '../utils/selectFieldOptions.js'

const fieldTypeOptions: Array<{ value: FieldType, label: string }> = [
	{ value: 'text', label: 'Text' },
	{ value: 'number', label: 'Number' },
	{ value: 'select', label: 'Select' },
]

const editPolicyOptions: Array<{ value: FieldEditPolicy, label: string }> = [
	{ value: 'users', label: 'Users can edit' },
	{ value: 'admins', label: 'Admins only' },
]

const exposurePolicyOptions: Array<{ value: FieldExposurePolicy, label: string }> = [
	{ value: 'hidden', label: 'Hidden from users' },
	{ value: 'private', label: 'Visible to users, private by default' },
	{ value: 'users', label: 'Visible to users, shared with users by default' },
	{ value: 'public', label: 'Visible to everyone by default' },
]

const definitions = ref<FieldDefinition[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const selectedId = ref<number | null>(null)
const justSavedId = ref<number | null>(null)
let justSavedTimeout: ReturnType<typeof setTimeout> | null = null

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
const isBulkOptionsDialogOpen = ref(false)
const isCompactLayout = ref(false)
const bulkOptionInput = ref('')
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
	? 'Only administrators can create or update stored values for this field.'
	: 'Users can maintain their own value for this field from personal settings.')
const exposurePolicyDescription = computed(() => {
	switch (form.exposurePolicy) {
	case 'hidden':
		return 'The field stays out of personal settings and regular global search.'
	case 'users':
		return 'The field appears in personal settings. New values are shared with logged-in users.'
	case 'public':
		return 'The field appears in personal settings. New values are public.'
	default:
		return 'The field appears in personal settings. New values start private.'
	}
})
const editorDescription = computed(() => isEditing.value
	? 'Update the selected field and its policies.'
	: 'Define the field and its policies.')
const selectedDefinition = computed(() => definitions.value.find((definition: FieldDefinition) => definition.id === selectedId.value) ?? null)
const sortedDefinitions = computed(() => [...definitions.value].sort((left, right) => left.sort_order - right.sort_order || left.id - right.id))
const isEditing = computed(() => selectedDefinition.value !== null)
const isEditorVisible = computed(() => isCreatingNew.value || isEditing.value)
const editorShellComponent = computed(() => isCompactLayout.value ? NcDialog : 'div')
const editorShellProps = computed(() => isCompactLayout.value
	? {
		open: isEditorVisible.value,
		name: isEditing.value ? 'Edit field' : 'Create field',
		size: 'large',
		contentClasses: 'profile-fields-admin__editor-dialog',
	}
	: {})
const editorEmptyState = computed(() => sortedDefinitions.value.length === 0
	? {
		title: 'No fields yet',
		description: 'Create the first field definition to start building the catalog.',
	}
	: {
		title: 'No field selected',
		description: 'Choose a field from the list to edit it, or create a new one.',
	})

const buildFormState = () => ({
	fieldKey: form.fieldKey,
	label: form.label,
	type: form.type,
	editPolicy: form.editPolicy,
	exposurePolicy: form.exposurePolicy,
	sortOrder: Number(form.sortOrder),
	active: form.active,
	options: form.type === 'select' ? extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '') : [],
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
		options: definition.type === 'select' ? (definition.options ?? []) : [],
	}
}

const isFormDirty = computed(() => JSON.stringify(buildFormState()) !== JSON.stringify(buildDefinitionState(selectedDefinition.value)))

const duplicateOptionIndices = computed(() => {
	const seen = new Map<string, number>()
	const duplicates = new Set<number>()
	form.options.forEach((option: EditableSelectOption, index: number) => {
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

const isOptionDuplicate = (index: number) => duplicateOptionIndices.value.has(index)
const hasDuplicateOptions = computed(() => duplicateOptionIndices.value.size > 0)
const hasOptionValue = (index: number) => form.options[index]?.value.trim() !== ''
const canMoveOptionUp = (index: number) => index > 0
const canMoveOptionDown = (index: number) => index < form.options.length - 1
const bulkOptionValues = computed(() => parseEditableSelectOptionValues(bulkOptionInput.value))
const normalizedOptionCount = computed(() => extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '').length)
const hasRequiredFields = computed(() => {
	if (form.fieldKey.trim() === '' || form.label.trim() === '') {
		return false
	}

	if (form.type === 'select' && normalizedOptionCount.value === 0) {
		return false
	}

	return true
})
const isSaveDisabled = computed(() => isSaving.value || !isFormDirty.value || hasDuplicateOptions.value || !hasRequiredFields.value)

const focusOptionInput = async(index: number) => {
	await nextTick()
	requestAnimationFrame(() => {
		const input = document.querySelector<HTMLInputElement>(`[data-testid="profile-fields-admin-option-row-${index}"] input`)
		input?.focus()
	})
}

const firstEmptyOptionIndex = () => form.options.findIndex((option: EditableSelectOption) => option.value.trim() === '')

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
	...(definition.type === 'select' ? { options: definition.options ?? [] } : {}),
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
	form.options = definition.type === 'select'
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
		errorMessage.value = error instanceof Error ? error.message : 'Failed to load field definitions.'
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
		...(form.type === 'select'
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
		} else {
			const updated = await updateDefinition(selectedDefinition.value.id, {
				label: payload.label,
				type: payload.type,
				editPolicy: payload.editPolicy,
				exposurePolicy: payload.exposurePolicy,
				sortOrder: payload.sortOrder,
				active: payload.active,
				...(payload.type === 'select' ? { options: payload.options } : {}),
			})
			replaceDefinitionInState(updated)
			populateForm(updated)
			markJustSaved(updated.id)
		}
		if (isCompactLayout.value) {
			closeEditor()
		}
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to save field definition.'
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
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to delete field definition.'
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
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to reorder field definitions.'
	} finally {
		isSaving.value = false
	}
}

const addOption = async() => {
	const existingEmptyIndex = firstEmptyOptionIndex()
	if (existingEmptyIndex !== -1) {
		await focusOptionInput(existingEmptyIndex)
		return
	}

	form.options.push({
		id: createOptionId(),
		value: '',
	})

	await focusOptionInput(form.options.length - 1)
}

const updateOption = (index: number, value: string) => {
	if (form.options[index] === undefined) {
		return
	}

	form.options[index].value = value
}

const addOptionFromEnter = async(index: number, event: KeyboardEvent) => {
	const input = event.target instanceof HTMLInputElement ? event.target : null
	if (input !== null) {
		updateOption(index, input.value)
	}

	await addOption()
}

const cleanupEmptyOptionOnBlur = (optionId: string) => {
	window.setTimeout(() => {
		const index = form.options.findIndex((option: EditableSelectOption) => option.id === optionId)
		if (index === -1) {
			return
		}

		if (form.options[index].value.trim() !== '') {
			return
		}

		const hasNonEmptySibling = form.options.some((option: EditableSelectOption, optionIndex: number) => optionIndex !== index && option.value.trim() !== '')
		if (!hasNonEmptySibling) {
			return
		}

		removeOption(index)
	}, 0)
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

	form.options = [
		...form.options.filter((option: EditableSelectOption) => option.value.trim() !== ''),
		...createEditableSelectOptions(bulkOptionValues.value, createOptionId),
	]

	closeBulkOptionsDialog()
	await addOption()
}

const removeEmptyOptionFromKeyboard = async(index: number, event: KeyboardEvent) => {
	const input = event.target instanceof HTMLInputElement ? event.target : null
	if (input === null || input.value !== '' || form.options.length <= 1) {
		return
	}

	event.preventDefault()
	removeOption(index)
	await focusOptionInput(Math.max(0, index - 1))
}

const moveOption = (index: number, direction: -1 | 1) => {
	form.options = moveEditableSelectOption(form.options, index, direction)
}

const removeOption = (index: number) => {
	form.options.splice(index, 1)
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
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to update field definition.'
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
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to delete field definition.'
	} finally {
		isSaving.value = false
	}
}

watch(() => form.type, (newType: FieldType) => {
	if (newType === 'select') {
		if (form.options.length === 0) {
			void addOption()
		}
		return
	}

	if (newType !== 'select') {
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
})
</script>

<style scoped lang="scss">
.profile-fields-admin {
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

		> div:first-child {
			padding-inline-start: clamp(18px, 2.4vw, 28px);
		}

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

		> div:first-child {
			flex: 1 1 auto;
			min-width: 0;
		}

		> :deep(.button-vue) {
			flex: 0 0 auto;
		}

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

	&__section-heading--split {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		gap: 16px;
	}

	&__options-meta {
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

	&__bulk-options-content {
		display: grid;
		gap: 12px;
	}

	&__bulk-options-summary {
		margin: 0;
		font-size: 13px;
		color: var(--color-text-maxcontrast);
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

	&__option-toolbar {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
	}

	&__options-editor {
		display: grid;
		gap: 8px;
	}

	&__option-row {
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

	&__option-leading,
	&__option-actions {
		display: flex;
		align-items: flex-start;
		flex: 0 0 auto;
	}

	&__option-leading {
		min-width: 42px;
	}

	&__option-handle,
	&__option-handle-spacer {
		width: 42px;
	}

	&__option-handle {
		:deep(.action-item),
		:deep(.action-item__wrapper) {
			width: 100%;
		}

		:deep(.button-vue) {
			width: 100%;
			cursor: grab;
		}
	}

	&__option-handle-spacer {
		height: 34px;
	}

	&__option-row--ghost {
		opacity: 0.45;
	}

	&__option-row--chosen {
		box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 22%, transparent);
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

		&__section-heading--split {
			flex-direction: column;
		}

		&__options-meta {
			justify-items: start;
		}
	}
}

@media (max-width: 1280px) {
	.profile-fields-admin {
		&__layout {
			grid-template-columns: 1fr;
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

			> div:first-child {
				padding-inline-start: 28px;
			}
		}
	}
}
</style>
