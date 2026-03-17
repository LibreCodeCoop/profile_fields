<!--
SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors

SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="profile-fields-workflow-targets">
		<NcSelect
			:model-value="modelValue"
			:multiple="true"
			:disabled="disabled"
			:options="options"
			label="displayName"
			:input-label="inputLabel"
			:placeholder="inputLabel"
			:loading="loading"
			@search="$emit('search', $event)"
			@update:model-value="$emit('update:modelValue', $event)">
			<template #option="{ displayName, subname, isNoUser }">
				<div class="profile-fields-workflow-target-option">
					<NcAvatar
						class="profile-fields-workflow-target-option__avatar"
						:disable-menu="true"
						:disable-tooltip="true"
						:display-name="displayName"
						:is-no-user="isNoUser ?? false"
						:size="32" />

					<div class="profile-fields-workflow-target-option__details">
						<span class="profile-fields-workflow-target-option__lineone">{{ displayName }}</span>
						<span v-if="subname" class="profile-fields-workflow-target-option__linetwo">{{ subname }}</span>
					</div>
				</div>
			</template>

			<template #selected-option="{ displayName }">
				<span class="profile-fields-workflow-targets__selected">
					<NcEllipsisedOption class="profile-fields-workflow-targets__selected-label" :name="displayName" />
				</span>
			</template>
		</NcSelect>

		<div class="profile-fields-workflow-targets__helper">
			{{ helperText }}
		</div>
	</div>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import type { PropType } from 'vue'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcEllipsisedOption from '@nextcloud/vue/components/NcEllipsisedOption'
import NcSelect from '@nextcloud/vue/components/NcSelect'

type WorkflowTargetOption = {
	id: string
	displayName: string
	subname?: string
	user?: string
	isNoUser?: boolean
}

export default defineComponent({
	name: 'WorkflowTargetsSelect',
	components: {
		NcAvatar,
		NcEllipsisedOption,
		NcSelect,
	},
	props: {
		modelValue: {
			type: Array as PropType<WorkflowTargetOption[]>,
			required: true,
		},
		options: {
			type: Array as PropType<WorkflowTargetOption[]>,
			required: true,
		},
		disabled: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			required: true,
		},
		inputLabel: {
			type: String,
			required: true,
		},
		helperText: {
			type: String,
			required: true,
		},
	},
	emits: {
		search: (value: string) => typeof value === 'string',
		'update:modelValue': (_value: WorkflowTargetOption[] | WorkflowTargetOption | null) => true,
	},
})
</script>

<style>
.profile-fields-workflow-targets {
	display: grid;
	gap: .5rem;
}

.profile-fields-workflow-targets__helper {
	color: var(--color-text-maxcontrast);
	font-size: .85rem;
}

.profile-fields-workflow-targets__selected {
	display: inline-block;
	max-width: 100%;
}

.profile-fields-workflow-targets__selected-label {
	display: block;
	max-width: 100%;
}

.profile-fields-workflow-target-option {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	min-width: 0;
}

.profile-fields-workflow-target-option__avatar {
	flex: 0 0 auto;
}

.profile-fields-workflow-target-option__details {
	display: flex;
	flex: 1 1 auto;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
}

.profile-fields-workflow-target-option__lineone,
.profile-fields-workflow-target-option__linetwo {
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	line-height: 1.2;
}

.profile-fields-workflow-target-option__lineone {
	color: var(--color-main-text);
}

.profile-fields-workflow-target-option__linetwo {
	color: var(--color-text-maxcontrast);
	font-size: 11px;
	margin-top: -2px;
}
</style>
