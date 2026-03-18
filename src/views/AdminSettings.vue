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
					Create the global field catalog, control who can edit each field and tune the default visibility used when a value is first stored.
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
		<NcNoteCard v-if="successMessage" type="success" data-testid="profile-fields-admin-success">
			{{ successMessage }}
		</NcNoteCard>

		<div v-if="isLoading" class="profile-fields-admin__loading">
			<NcLoadingIcon :size="32" />
		</div>

		<div v-else class="profile-fields-admin__layout">
			<aside class="profile-fields-admin__list-panel">
				<div class="profile-fields-admin__panel-header">
					<div>
						<h3>Defined fields</h3>
						<p>Pick an existing definition to edit its rules or start a fresh field.</p>
					</div>
					<NcButton variant="secondary" data-testid="profile-fields-admin-new-field" @click="startCreatingField">
						New field
					</NcButton>
				</div>

				<NcEmptyContent v-if="sortedDefinitions.length === 0" name="No fields yet" description="The catalog is empty. Create the first field to make it available to users." />

				<ul v-else class="profile-fields-admin__list">
					<li v-for="definition in sortedDefinitions" :key="definition.id">
						<button
							class="profile-fields-admin__list-item"
							:class="{ 'is-selected': definition.id === selectedId }"
							:data-testid="`profile-fields-admin-definition-${definition.field_key}`"
							@click="populateForm(definition)">
							<div>
								<strong>{{ definition.label }}</strong>
								<span>{{ definition.field_key }}</span>
							</div>
							<div class="profile-fields-admin__list-item-meta">
								<span>{{ definition.type }}</span>
								<span :class="definition.active ? 'is-active' : 'is-inactive'">{{ definition.active ? 'Active' : 'Inactive' }}</span>
							</div>
						</button>
					</li>
				</ul>
			</aside>

			<div class="profile-fields-admin__editor">
				<template v-if="isEditorVisible">
					<div class="profile-fields-admin__panel-header">
						<div>
							<h3>{{ isEditing ? 'Edit field' : 'Create field' }}</h3>
							<p>Configure the label, storage rules and who is allowed to manage the value.</p>
						</div>
						<div class="profile-fields-admin__editor-actions">
							<NcButton variant="secondary" :disabled="!canMoveUp || isSaving" @click="moveDefinition(-1)">
								Move up
							</NcButton>
							<NcButton variant="secondary" :disabled="!canMoveDown || isSaving" @click="moveDefinition(1)">
								Move down
							</NcButton>
						</div>
					</div>

					<form class="profile-fields-admin__form" data-testid="profile-fields-admin-form" @submit.prevent="persistDefinition">
					<section class="profile-fields-admin__form-section profile-fields-admin__form-section--identity">
						<div class="profile-fields-admin__section-heading">
							<h4>Identity</h4>
							<p>The key is immutable after creation and should stay API-safe.</p>
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
							<h4>Behavior</h4>
							<p>Choose how the field is stored, displayed and exposed by default.</p>
						</div>

						<div class="profile-fields-admin__grid">
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

							<div class="profile-fields-admin__field">
								<label for="profile-fields-admin-visibility">Initial visibility</label>
								<NcSelect
									input-id="profile-fields-admin-visibility"
									v-model="selectedVisibilityOption"
									input-label="Initial visibility"
									label-outside
									:clearable="false"
									:searchable="false"
									:options="visibilityOptions"
									label="label"
									placeholder="Choose default visibility"
								/>
							</div>
						</div>
					</section>

					<section v-if="form.type === 'select'" class="profile-fields-admin__form-section">
						<div class="profile-fields-admin__section-heading">
							<h4>Options</h4>
							<p>Define the values users and admins can pick from this field.</p>
							<p class="profile-fields-admin__section-hint">Press Enter to create the next option. Empty rows are reused, removed on blur, and Backspace or Delete removes an empty row. Use Add multiple options to paste one option per line.</p>
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

					<section class="profile-fields-admin__form-section">
						<div class="profile-fields-admin__section-heading">
							<h4>Permissions</h4>
							<p>Control whether users can write the field directly and whether the definition stays active.</p>
						</div>

						<div class="profile-fields-admin__toggles">
							<NcCheckboxRadioSwitch v-model="form.adminOnly" type="switch" class="profile-fields-admin__toggle-card">
								Admin-only editing
								<template #description>
									Only administrators can update stored values. Enabling this turns off user self-service editing.
								</template>
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-model="form.userEditable" type="switch" class="profile-fields-admin__toggle-card" :disabled="!form.userVisible">
								User self-service editing
								<template #description>
									Expose the field in the personal settings page. Enabling this turns off admin-only editing.
								</template>
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-model="form.userVisible" type="switch" class="profile-fields-admin__toggle-card">
								Visible to users
								<template #description>
									{{ userVisibleDescription }}
								</template>
							</NcCheckboxRadioSwitch>
							<NcCheckboxRadioSwitch v-model="form.active" type="switch" class="profile-fields-admin__toggle-card">
								Field is active
								<template #description>
									Inactive fields stay defined but disappear from the UX.
								</template>
							</NcCheckboxRadioSwitch>
						</div>
					</section>

					<div class="profile-fields-admin__submit-row">
						<NcButton type="submit" variant="primary" data-testid="profile-fields-admin-save" :disabled="isSaving || !isFormDirty || hasDuplicateOptions">
							{{ isSaving ? 'Saving...' : (isEditing ? 'Save changes' : 'Create field') }}
						</NcButton>
						<NcButton v-if="isEditing" variant="error" data-testid="profile-fields-admin-delete" :disabled="isSaving" @click.prevent="removeDefinition">
							Delete field
						</NcButton>
					</div>
					</form>
				</template>

				<div v-else class="profile-fields-admin__empty-editor">
					<NcEmptyContent :name="editorEmptyState.title" :description="editorEmptyState.description" />
					<NcButton variant="primary" @click="startCreatingField">
						New field
					</NcButton>
				</div>
			</div>
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
import { mdiArrowDown, mdiArrowUp, mdiClose, mdiDragVertical } from '@mdi/js'
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import Draggable from 'vuedraggable'
import { NcActionButton, NcActions, NcButton, NcCheckboxRadioSwitch, NcEmptyContent, NcIconSvgWrapper, NcInputField, NcLoadingIcon, NcNoteCard, NcSelect } from '@nextcloud/vue'
import { createDefinition, deleteDefinition, listDefinitions, updateDefinition } from '../api'
import type { FieldDefinition, FieldType, FieldVisibility } from '../types'
import { buildFieldOrderUpdates } from '../utils/fieldOrder.js'
import { createEditableSelectOptions, extractEditableSelectOptionValues, moveEditableSelectOption, normalizeEditableSelectOptionValue, parseEditableSelectOptionValues } from '../utils/selectFieldOptions.js'
import type { EditableSelectOption } from '../utils/selectFieldOptions.js'
import { visibilityOptions } from '../utils/visibilityOptions.js'

const fieldTypeOptions: Array<{ value: FieldType, label: string }> = [
	{ value: 'text', label: 'Text' },
	{ value: 'number', label: 'Number' },
	{ value: 'select', label: 'Select' },
]

const definitions = ref<FieldDefinition[]>([])
const isLoading = ref(true)
const isSaving = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const selectedId = ref<number | null>(null)
const isCreatingNew = ref(false)
const isBulkOptionsDialogOpen = ref(false)
const bulkOptionInput = ref('')
let nextOptionId = 0

const createOptionId = () => `option-${nextOptionId++}`

const form = reactive({
	fieldKey: '',
	label: '',
	type: 'text' as FieldType,
	adminOnly: false,
	userEditable: true,
	userVisible: true,
	initialVisibility: 'private' as FieldVisibility,
	sortOrder: 0,
	active: true,
	options: createEditableSelectOptions([], createOptionId),
})


const userVisibleDescription = computed(() => form.userVisible
	? 'Show the field in admin and personal user-facing settings.'
	: 'Hide system-managed fields from user-facing settings.')
const selectedDefinition = computed(() => definitions.value.find((definition: FieldDefinition) => definition.id === selectedId.value) ?? null)
const sortedDefinitions = computed(() => [...definitions.value].sort((left, right) => left.sort_order - right.sort_order || left.id - right.id))
const isEditing = computed(() => selectedDefinition.value !== null)
const isEditorVisible = computed(() => isCreatingNew.value || isEditing.value)
const selectedDefinitionIndex = computed(() => sortedDefinitions.value.findIndex((definition: FieldDefinition) => definition.id === selectedId.value))
const canMoveUp = computed(() => isEditing.value && selectedDefinitionIndex.value > 0)
const canMoveDown = computed(() => isEditing.value && selectedDefinitionIndex.value > -1 && selectedDefinitionIndex.value < sortedDefinitions.value.length - 1)
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
	adminOnly: form.adminOnly,
	userEditable: form.userEditable,
	userVisible: form.userVisible,
	initialVisibility: form.initialVisibility,
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
			adminOnly: false,
			userEditable: true,
			userVisible: true,
			initialVisibility: 'private' as FieldVisibility,
			sortOrder: definitions.value.length,
			active: true,
			options: [],
		}
	}

	return {
		fieldKey: definition.field_key,
		label: definition.label,
		type: definition.type,
		adminOnly: definition.admin_only,
		userEditable: definition.user_editable,
		userVisible: definition.user_visible,
		initialVisibility: definition.initial_visibility,
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
const selectedVisibilityOption = computed({
	get: () => visibilityOptions.find((option) => option.value === form.initialVisibility) ?? visibilityOptions[0],
	set: (option: { value: FieldVisibility, label: string } | null) => {
		if (option !== null) {
			form.initialVisibility = option.value
		}
	},
})

const resetForm = () => {
	selectedId.value = null
	form.fieldKey = ''
	form.label = ''
	form.type = 'text'
	form.adminOnly = false
	form.userEditable = true
	form.userVisible = true
	form.initialVisibility = 'private'
	form.sortOrder = definitions.value.length
	form.active = true
	form.options = createEditableSelectOptions([], createOptionId)
}

const startCreatingField = () => {
	isCreatingNew.value = true
	resetForm()
}

const populateForm = (definition: FieldDefinition) => {
	isCreatingNew.value = false
	selectedId.value = definition.id
	form.fieldKey = definition.field_key
	form.label = definition.label
	form.type = definition.type
	form.adminOnly = definition.admin_only
	form.userEditable = definition.user_editable
	form.userVisible = definition.user_visible
	form.initialVisibility = definition.initial_visibility
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
	successMessage.value = ''

	const payload = {
		fieldKey: form.fieldKey,
		label: form.label,
		type: form.type,
		adminOnly: form.adminOnly,
		userEditable: form.userEditable,
		userVisible: form.userVisible,
		initialVisibility: form.initialVisibility,
		sortOrder: Number(form.sortOrder),
		active: form.active,
		...(form.type === 'select'
			? { options: extractEditableSelectOptionValues(form.options).filter((optionValue: string) => optionValue.trim() !== '') }
			: {}),
	}

	try {
		if (selectedDefinition.value === null) {
			const created = await createDefinition(payload)
			selectedId.value = created.id
			successMessage.value = 'Field definition created.'
		} else {
			await updateDefinition(selectedDefinition.value.id, {
				label: payload.label,
				type: payload.type,
				adminOnly: payload.adminOnly,
				userEditable: payload.userEditable,
				userVisible: payload.userVisible,
				initialVisibility: payload.initialVisibility,
				sortOrder: payload.sortOrder,
				active: payload.active,
				...(payload.type === 'select' ? { options: payload.options } : {}),
			})
			successMessage.value = 'Field definition updated.'
		}
		await loadDefinitions()
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
	successMessage.value = ''
	try {
		await deleteDefinition(selectedDefinition.value.id)
		successMessage.value = 'Field definition deleted.'
		isCreatingNew.value = false
		resetForm()
		await loadDefinitions()
	} catch (error: any) {
		errorMessage.value = error?.response?.data?.ocs?.data?.message ?? error?.message ?? 'Failed to delete field definition.'
	} finally {
		isSaving.value = false
	}
}

const moveDefinition = async(direction: -1 | 1) => {
	if (selectedDefinition.value === null) {
		return
	}

	const updates = buildFieldOrderUpdates(definitions.value, selectedDefinition.value.id, direction)
	if (updates.length === 0) {
		return
	}

	isSaving.value = true
	errorMessage.value = ''
	successMessage.value = ''

	try {
		const current = selectedDefinition.value
		await Promise.all(updates.map(({ id, sortOrder }) => {
			const definition = definitions.value.find((candidate: FieldDefinition) => candidate.id === id)
			if (definition === undefined) {
				return Promise.resolve(null)
			}

			return updateDefinition(id, {
				label: definition.label,
				type: definition.type,
				adminOnly: definition.admin_only,
				userEditable: definition.user_editable,
				userVisible: definition.user_visible,
				initialVisibility: definition.initial_visibility,
				sortOrder,
				active: definition.active,
				...(definition.type === 'select' ? { options: definition.options ?? [] } : {}),
			})
		}))

		const nextSortOrder = updates.find((update) => update.id === current.id)?.sortOrder
		if (nextSortOrder !== undefined) {
			form.sortOrder = nextSortOrder
		}
		successMessage.value = 'Field order updated.'
		await loadDefinitions()
	} catch (error: any) {
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

watch(() => form.userVisible, (userVisible: boolean) => {
	if (!userVisible) {
		form.userEditable = false
	}
})

watch(() => form.adminOnly, (adminOnly: boolean) => {
	if (adminOnly) {
		form.userEditable = false
	}
})

watch(() => form.userEditable, (userEditable: boolean) => {
	if (userEditable) {
		form.adminOnly = false
	}
})

onMounted(loadDefinitions)
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
			max-width: 62ch;
			color: color-mix(in srgb, var(--color-main-text) 82%, transparent);
		}
	}

	&__hero-meta {
		display: grid;
		align-content: center;
		justify-items: end;
		min-width: 140px;
		padding: 14px 16px;
		border-radius: 16px;
		background: color-mix(in srgb, var(--color-main-background) 86%, transparent);

		strong {
			font-size: 40px;
			line-height: 1;
		}

		span {
			color: var(--color-text-maxcontrast);
		}
	}

	&__layout {
		display: grid;
		grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
		gap: 20px;
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
		flex: 0 0 auto;
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

	&__list-item {
		width: 100%;
		padding: 14px;
		border-radius: 16px;
		border: 1px solid var(--color-border-default);
		background: color-mix(in srgb, var(--color-main-background) 92%, var(--color-background-hover) 8%);
		display: flex;
		justify-content: space-between;
		gap: 12px;
		text-align: left;
		cursor: pointer;
		transition: border-color 120ms ease, transform 120ms ease, background 120ms ease;

		&:hover {
			transform: translateY(-1px);
			border-color: color-mix(in srgb, var(--color-primary-element) 28%, var(--color-border-default) 72%);
		}

		&.is-selected {
			border-color: var(--color-primary-element);
			box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-primary-element) 20%, transparent);
		}

		strong,
		span {
			display: block;
		}

		span {
			font-size: 12px;
			color: var(--color-text-maxcontrast);
		}
	}

	&__list-item-meta {
		display: grid;
		justify-items: end;
		align-content: start;
		gap: 6px;

		span:first-child {
			padding: 4px 8px;
			border-radius: 999px;
			background: color-mix(in srgb, var(--color-primary-element) 12%, transparent);
			color: var(--color-main-text);
			text-transform: capitalize;
		}
	}

	.is-active {
		color: #0b7a38 !important;
	}

	.is-inactive {
		color: #9b3d16 !important;
	}

	&__form {
		display: grid;
		gap: 16px;
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

		p {
			margin: 6px 0 0;
			color: var(--color-text-maxcontrast);
		}
	}

	&__section-hint {
		font-size: 12px;
		line-height: 1.4;
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
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
		gap: 14px;
		align-items: start;

		&--identity {
			grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
		}
	}

	&__toggles {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
		gap: 10px 16px;
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
		}
	}

	&__submit-row {
		display: flex;
		gap: 10px;
		justify-content: flex-end;
		padding-top: 4px;
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
		justify-items: start;
		gap: 18px;
		padding: 28px 8px;

		:deep(.empty-content) {
			margin: 0;
		}
	}

	&__loading {
		display: flex;
		justify-content: center;
		padding: 40px 0;
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
		}

		&__editor-actions {
			flex-wrap: wrap;
			justify-content: flex-start;
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
		&__toggles {
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
			padding: 20px;

			> div:first-child {
				padding-inline-start: 36px;
			}
		}
	}
}
</style>
