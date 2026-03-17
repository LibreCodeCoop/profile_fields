// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { t } from '@nextcloud/l10n'

import { listDefinitions } from './api.ts'
import type { FieldDefinition } from './types/index.ts'
import {
	getWorkflowOperatorKeys,
	isWorkflowOperatorSupported,
	parseWorkflowCheckValue,
	serializeWorkflowCheckValue,
	workflowOperatorRequiresValue,
	type WorkflowCheckDefinition,
} from './utils/workflowProfileFieldCheck.ts'

type WorkflowOperator = {
	operator: string
	name: string
}

type WorkflowEnginePlugin = {
	class: string
	name: string
	operators: (check: { value?: string | null }) => WorkflowOperator[]
	element: string
}

type WorkflowEngineOperatorPlugin = {
	id: string
	operation: string
	color: string
	element?: string
}

type WorkflowEngineApi = {
	registerCheck: (plugin: WorkflowEnginePlugin) => void
	registerOperator: (plugin: WorkflowEngineOperatorPlugin) => void
}

type WorkflowEngineEntity = {
	id: string
	events: Array<{
		eventName: string
		displayName: string
	}>
}

type WorkflowEngineRule = {
	id: number
	class: string
	entity: string
	events: string[]
	name: string
	checks: Array<{ class: string | null, operator: string | null, value: string }>
	operation: string
	valid?: boolean
}

type WorkflowEngineStore = {
	state: {
		rules: WorkflowEngineRule[]
		entities: WorkflowEngineEntity[]
	}
	commit: (type: string, payload?: WorkflowEngineRule) => void
	dispatch: (type: string, payload?: unknown) => Promise<unknown> | unknown
}

type WorkflowEngineRootVm = {
	$store: WorkflowEngineStore
	createNewRule: (operation: { id: string }) => Promise<unknown> | unknown
}

const workflowCheckClass = 'OCA\\ProfileFields\\Workflow\\UserProfileFieldCheck'
const workflowOperationClasses = [
	'OCA\\ProfileFields\\Workflow\\LogProfileFieldChangeOperation',
	'OCA\\ProfileFields\\Workflow\\NotifyUserProfileFieldChangeOperation',
	'OCA\\ProfileFields\\Workflow\\SendWebhookProfileFieldChangeOperation',
]
const workflowEntityClass = 'OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity'
const workflowUpdatedEventClass = 'OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent'
const workflowElementId = 'oca-profile-fields-check-user-profile-field'
const webhookOperationElementId = 'oca-profile-fields-webhook-operation'
const workflowOperationNames = new Set([
	t('profile_fields', 'Log profile field change'),
	t('profile_fields', 'Notify affected user'),
	t('profile_fields', 'Send webhook'),
])
const workflowCardClassName = 'profile-fields-workflow-card'
const workflowItemClassName = 'profile-fields-workflow-item'
const workflowCardThemeStyleId = 'profile-fields-workflow-card-theme'

const operatorLabels: Record<string, string> = {
	'is-set': t('profile_fields', 'is set'),
	'!is-set': t('profile_fields', 'is not set'),
	'is': t('profile_fields', 'is'),
	'!is': t('profile_fields', 'is not'),
	'contains': t('profile_fields', 'contains'),
	'!contains': t('profile_fields', 'does not contain'),
	'less': t('profile_fields', 'is less than'),
	'!greater': t('profile_fields', 'is less than or equal to'),
	'greater': t('profile_fields', 'is greater than'),
	'!less': t('profile_fields', 'is greater than or equal to'),
}

let definitions: WorkflowCheckDefinition[] = []
let definitionsPromise: Promise<WorkflowCheckDefinition[]> | null = null

const toWorkflowDefinition = (definition: FieldDefinition): WorkflowCheckDefinition => ({
	field_key: definition.field_key,
	label: definition.label,
	type: definition.type,
	active: definition.active,
})

const loadDefinitions = async(): Promise<WorkflowCheckDefinition[]> => {
	if (definitionsPromise === null) {
		definitionsPromise = listDefinitions()
			.then((items) => items.map(toWorkflowDefinition).filter((item) => item.active))
			.catch(() => [])
			.then((items) => {
				definitions = items
				return items
			})
	}

	return definitionsPromise
}

const dispatchModelValue = (element: HTMLElement, value: string): void => {
	element.dispatchEvent(new CustomEvent('update:model-value', {
		detail: [value],
		bubbles: true,
		composed: true,
	}))
}

const dispatchValidity = (element: HTMLElement, valid: boolean): void => {
	element.dispatchEvent(new CustomEvent(valid ? 'valid' : 'invalid', {
		bubbles: true,
		composed: true,
	}))
}

class WorkflowProfileFieldElement extends HTMLElement {
	private modelValueInternal = ''
	private operatorInternal = 'is'
	private disabledInternal = false

	static get observedAttributes(): string[] {
		return ['model-value', 'operator', 'disabled']
	}

	connectedCallback(): void {
		this.syncFromAttributes()
		void loadDefinitions().then(() => this.render())
		this.render()
	}

	attributeChangedCallback(name: string, oldValue: string | null, newValue: string | null): void {
		if (oldValue === newValue) {
			return
		}

		switch (name) {
			case 'model-value':
				this.modelValueInternal = newValue ?? ''
				break
			case 'operator':
				this.operatorInternal = typeof newValue === 'string' && newValue !== '' ? newValue : 'is'
				break
			case 'disabled':
				this.disabledInternal = newValue === '' || newValue === 'true'
				break
			default:
				return
		}

		this.render()
	}

	set modelValue(value: string | null | undefined) {
		this.modelValueInternal = typeof value === 'string' ? value : ''
		this.render()
	}

	get modelValue(): string {
		return this.modelValueInternal
	}

	set operator(value: string | null | undefined) {
		this.operatorInternal = typeof value === 'string' && value !== '' ? value : 'is'
		this.render()
	}

	get operator(): string {
		return this.operatorInternal
	}

	set disabled(value: boolean | string | null | undefined) {
		this.disabledInternal = value === '' || value === true || value === 'true'
		this.render()
	}

	get disabled(): boolean {
		return this.disabledInternal
	}

	private syncFromAttributes(): void {
		this.modelValueInternal = this.getAttribute('model-value') ?? this.modelValueInternal
		this.operatorInternal = this.getAttribute('operator') || this.operatorInternal
		this.disabledInternal = this.getAttribute('disabled') === '' || this.getAttribute('disabled') === 'true'
	}

	private render(): void {
		const parsedValue = parseWorkflowCheckValue(this.modelValueInternal)
		const selectedFieldKey = parsedValue?.field_key ?? ''
		const currentValue = parsedValue?.value == null ? '' : String(parsedValue.value)
		const selectedDefinition = definitions.find((definition) => definition.field_key === selectedFieldKey) ?? null
		const operatorNeedsValue = workflowOperatorRequiresValue(this.operatorInternal)
		const isValid = selectedDefinition !== null
			&& isWorkflowOperatorSupported(this.operatorInternal, this.modelValueInternal, definitions)
			&& (!operatorNeedsValue || currentValue.trim() !== '')

		this.replaceChildren()

		const style = document.createElement('style')
		style.textContent = `
			:host {
				display: flex;
				flex: 1 1 22rem;
				gap: .5rem;
				align-items: center;
				min-width: 0;
			}

			select,
			input {
				border: 1px solid var(--color-border-maxcontrast);
				border-radius: var(--border-radius-element, 6px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				font: inherit;
				padding: .45rem .6rem;
				min-height: 2.25rem;
			}

			select {
				flex: 1 1 14rem;
				min-width: 10rem;
			}

			input {
				flex: 1 1 10rem;
				min-width: 8rem;
			}

			input[hidden] {
				display: none;
			}

			input.invalid,
			select.invalid {
				border-color: var(--color-error);
			}
		`

		const fieldSelect = document.createElement('select')
		fieldSelect.disabled = this.disabledInternal || definitions.length === 0
		fieldSelect.className = selectedDefinition === null ? 'invalid' : ''

		const placeholder = document.createElement('option')
		placeholder.value = ''
		placeholder.textContent = definitions.length === 0
			? t('profile_fields', 'Loading profile fields…')
			: t('profile_fields', 'Select a profile field')
		fieldSelect.append(placeholder)

		for (const definition of definitions) {
			const option = document.createElement('option')
			option.value = definition.field_key
			option.selected = definition.field_key === selectedFieldKey
			option.textContent = definition.label
			fieldSelect.append(option)
		}

		const valueInput = document.createElement('input')
		valueInput.type = selectedDefinition?.type === 'number' ? 'number' : 'text'
		valueInput.value = currentValue
		valueInput.disabled = this.disabledInternal || selectedDefinition === null || !operatorNeedsValue
		valueInput.hidden = !operatorNeedsValue
		valueInput.placeholder = selectedDefinition?.type === 'number'
			? t('profile_fields', 'Enter a numeric value')
			: t('profile_fields', 'Enter a comparison value')
		valueInput.className = !isValid && operatorNeedsValue ? 'invalid' : ''

		fieldSelect.addEventListener('change', () => {
			const nextFieldKey = fieldSelect.value
			const nextValue = serializeWorkflowCheckValue({
				field_key: nextFieldKey,
				value: valueInput.value === '' ? null : valueInput.value,
			})

			dispatchModelValue(this, nextValue)
		})

		valueInput.addEventListener('input', () => {
			if (fieldSelect.value === '') {
				return
			}

			const nextValue = serializeWorkflowCheckValue({
				field_key: fieldSelect.value,
				value: valueInput.value === '' ? null : valueInput.value,
			})

			dispatchModelValue(this, nextValue)
		})

		this.append(style, fieldSelect, valueInput)
		dispatchValidity(this, isValid)
	}
}

class WorkflowWebhookOperationElement extends HTMLElement {
	private modelValueInternal = ''
	private disabledInternal = false

	static get observedAttributes(): string[] {
		return ['model-value', 'disabled']
	}

	connectedCallback(): void {
		this.syncFromAttributes()
		this.render()
	}

	attributeChangedCallback(name: string, oldValue: string | null, newValue: string | null): void {
		if (oldValue === newValue) {
			return
		}

		if (name === 'model-value') {
			this.modelValueInternal = newValue ?? ''
		} else if (name === 'disabled') {
			this.disabledInternal = newValue === '' || newValue === 'true'
		}

		this.render()
	}

	set modelValue(value: string | null | undefined) {
		this.modelValueInternal = typeof value === 'string' ? value : ''
		this.render()
	}

	get modelValue(): string {
		return this.modelValueInternal
	}

	set disabled(value: boolean | string | null | undefined) {
		this.disabledInternal = value === '' || value === true || value === 'true'
		this.render()
	}

	get disabled(): boolean {
		return this.disabledInternal
	}

	private syncFromAttributes(): void {
		this.modelValueInternal = this.getAttribute('model-value') ?? this.modelValueInternal
		this.disabledInternal = this.getAttribute('disabled') === '' || this.getAttribute('disabled') === 'true'
	}

	private render(): void {
		const isValid = /^https?:\/\/.+/i.test(this.modelValueInternal.trim())

		this.replaceChildren()

		const style = document.createElement('style')
		style.textContent = `
			:host {
				display: flex;
				flex: 1 1 22rem;
				min-width: 0;
			}

			input {
				width: 100%;
				border: 1px solid var(--color-border-maxcontrast);
				border-radius: var(--border-radius-element, 6px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				font: inherit;
				padding: .45rem .6rem;
				min-height: 2.25rem;
			}

			input.invalid {
				border-color: var(--color-error);
			}
		`

		const input = document.createElement('input')
		input.type = 'url'
		input.value = this.modelValueInternal
		input.disabled = this.disabledInternal
		input.placeholder = t('profile_fields', 'Enter a webhook URL')
		input.className = this.modelValueInternal === '' || isValid ? '' : 'invalid'
		input.addEventListener('input', () => {
			dispatchModelValue(this, input.value)
		})

		this.append(style, input)
	}
}

if (!window.customElements.get(workflowElementId)) {
	window.customElements.define(workflowElementId, WorkflowProfileFieldElement)
}

if (!window.customElements.get(webhookOperationElementId)) {
	window.customElements.define(webhookOperationElementId, WorkflowWebhookOperationElement)
}

const buildOperators = (check: { value?: string | null }): WorkflowOperator[] => getWorkflowOperatorKeys(check.value ?? null, definitions).map((operator) => ({
	operator,
	name: operatorLabels[operator] ?? operator,
}))

const plugin: WorkflowEnginePlugin = {
	class: workflowCheckClass,
	name: t('profile_fields', 'Profile field value'),
	operators: buildOperators,
	element: workflowElementId,
}

const operationPlugins: WorkflowEngineOperatorPlugin[] = [
	{
		id: 'OCA\\ProfileFields\\Workflow\\LogProfileFieldChangeOperation',
		operation: '',
		color: 'var(--color-success)',
	},
	{
		id: 'OCA\\ProfileFields\\Workflow\\NotifyUserProfileFieldChangeOperation',
		operation: '',
		color: 'var(--color-success)',
	},
	{
		id: 'OCA\\ProfileFields\\Workflow\\SendWebhookProfileFieldChangeOperation',
		operation: '',
		color: 'var(--color-success)',
		element: webhookOperationElementId,
	},
]

const ensureWorkflowCardThemeStyle = (): void => {
	if (document.getElementById(workflowCardThemeStyleId) !== null) {
		return
	}

	const style = document.createElement('style')
	style.id = workflowCardThemeStyleId
	style.textContent = `
		.actions__item.${workflowItemClassName} {
			color: var(--color-main-text);
		}

		.actions__item.${workflowItemClassName} .actions__item__description h3,
		.actions__item.${workflowItemClassName} .actions__item__description small,
		.actions__item.${workflowItemClassName} .actions__item__description {
			color: var(--color-main-text);
		}

		.actions__item.${workflowItemClassName} .actions__item__description small {
			color: color-mix(in srgb, var(--color-main-text) 78%, transparent);
		}

		.actions__item.${workflowItemClassName} .icon {
			background-color: currentColor;
			background-image: none !important;
			mask-image: var(--profile-fields-workflow-icon);
			-webkit-mask-image: var(--profile-fields-workflow-icon);
			mask-repeat: no-repeat;
			-webkit-mask-repeat: no-repeat;
			mask-position: center;
			-webkit-mask-position: center;
			mask-size: contain;
			-webkit-mask-size: contain;
			filter: none !important;
		}
	`

	document.head.append(style)
}

const isWorkflowOperationCard = (element: Element): boolean => {
	const heading = element.querySelector<HTMLHeadingElement>('.actions__item__description h3')
	return workflowOperationNames.has(heading?.textContent?.trim() ?? '')
}

const applyWorkflowCardTheme = (): void => {
	ensureWorkflowCardThemeStyle()

	for (const card of document.querySelectorAll('.actions__item')) {
		if (!isWorkflowOperationCard(card)) {
			continue
		}

		card.classList.add(workflowItemClassName)
		if (card.classList.contains('colored')) {
			card.classList.add(workflowCardClassName)
		}

		const icon = card.querySelector<HTMLElement>('.icon')
		const backgroundImage = icon?.style.backgroundImage || (icon === null || icon === undefined ? '' : window.getComputedStyle(icon).backgroundImage)
		if (icon !== null && backgroundImage !== '' && backgroundImage !== 'none') {
			icon.style.setProperty('--profile-fields-workflow-icon', backgroundImage)
		}

		const addFlowButton = card.querySelector<HTMLButtonElement>('button')
		if (card.classList.contains('colored') && addFlowButton !== null && addFlowButton.dataset.profileFieldsWorkflowTriggerBound !== 'true') {
			addFlowButton.dataset.profileFieldsWorkflowTriggerBound = 'true'
			addFlowButton.addEventListener('click', () => {
				window.setTimeout(() => {
					const store = getWorkflowStore()
					if (store !== null) {
						applyDefaultTriggerToNewestWorkflowRule(store)
					}
				}, 0)
			})
		}
	}
}

let workflowCardThemeObserver: MutationObserver | null = null
let workflowDefaultsPatchAttempts = 0
let workflowDefaultsPatched = false

const observeWorkflowCards = (): void => {
	applyWorkflowCardTheme()

	if (workflowCardThemeObserver !== null || document.body === null) {
		return
	}

	workflowCardThemeObserver = new MutationObserver(() => applyWorkflowCardTheme())
	workflowCardThemeObserver.observe(document.body, {
		childList: true,
		subtree: true,
	})
}

const startWorkflowCardTheme = (): void => {
	applyWorkflowCardTheme()

	if (document.body !== null) {
		observeWorkflowCards()
		return
	}

	document.addEventListener('DOMContentLoaded', () => observeWorkflowCards(), { once: true })
	window.setTimeout(() => observeWorkflowCards(), 0)
}

const getWorkflowRootVm = (): WorkflowEngineRootVm | null => {
	const root = document.querySelector('#workflowengine') as (HTMLElement & { __vue__?: WorkflowEngineRootVm }) | null
	return root?.__vue__ ?? null
}

const getWorkflowStore = (): WorkflowEngineStore | null => {
	return getWorkflowRootVm()?.$store ?? null
}

const getDefaultWorkflowEventName = (store: WorkflowEngineStore): string | null => {
	const entity = store.state.entities.find((item) => item.id === workflowEntityClass)
	if (entity === undefined) {
		return null
	}

	return entity.events.find((event) => event.eventName === workflowUpdatedEventClass)?.eventName
		?? entity.events[0]?.eventName
		?? null
}

const applyDefaultTriggerToNewestWorkflowRule = (store: WorkflowEngineStore): void => {
	const defaultEventName = getDefaultWorkflowEventName(store)
	if (defaultEventName === null) {
		return
	}

	const targetRule = [...store.state.rules]
		.reverse()
		.find((rule) => workflowOperationClasses.includes(rule.class) && rule.id < 0)

	if (targetRule === undefined) {
		return
	}

	if (targetRule.entity === workflowEntityClass && targetRule.events.length === 1 && targetRule.events[0] === defaultEventName) {
		return
	}

	store.commit('updateRule', {
		...targetRule,
		entity: workflowEntityClass,
		events: [defaultEventName],
	})
}

const patchWorkflowCreateRuleDefaults = (): void => {
	if (workflowDefaultsPatched) {
		return
	}

	const store = getWorkflowStore()
	const rootVm = getWorkflowRootVm()
	if (store === null || rootVm === null) {
		if (workflowDefaultsPatchAttempts >= 20) {
			return
		}

		workflowDefaultsPatchAttempts += 1
		window.setTimeout(patchWorkflowCreateRuleDefaults, 50)
		return
	}

	workflowDefaultsPatched = true
}

void loadDefinitions()
startWorkflowCardTheme()
patchWorkflowCreateRuleDefaults()

let registrationAttempts = 0

const getWorkflowEngineApi = (): WorkflowEngineApi | null => {
	const workflowEngine = (window as Window & { OCA?: { WorkflowEngine?: WorkflowEngineApi } }).OCA?.WorkflowEngine
	if (!workflowEngine || typeof workflowEngine.registerCheck !== 'function' || typeof workflowEngine.registerOperator !== 'function') {
		return null
	}

	return workflowEngine
}

const registerWorkflowPlugins = (): void => {
	const workflowEngine = getWorkflowEngineApi()
	if (workflowEngine !== null) {
		workflowEngine.registerCheck(plugin)
		for (const operationPlugin of operationPlugins) {
			workflowEngine.registerOperator(operationPlugin)
		}
		return
	}

	if (registrationAttempts >= 20) {
		return
	}

	registrationAttempts += 1
	window.setTimeout(registerWorkflowPlugins, 50)
}

registerWorkflowPlugins()
