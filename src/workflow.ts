// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { t } from '@nextcloud/l10n'
import WorkflowTargetsSelect from './components/WorkflowTargetsSelect.vue'
import { createApp, h, reactive, type App } from 'vue'

import { listDefinitions, searchWorkflowTargetSuggestions, type WorkflowTargetSuggestion } from './api.ts'
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

type WorkflowEmailOperationConfig = {
	subjectTemplate: string
	bodyTemplate: string
}

type WorkflowTargetsOperationConfig = {
	targets: string
}

type WorkflowTargetOption = {
	id: string
	displayName: string
	subname?: string
	user?: string
	isNoUser?: boolean
}

type WorkflowTargetsViewState = {
	disabled: boolean
	loading: boolean
	options: WorkflowTargetOption[]
	selected: WorkflowTargetOption[]
}

type WorkflowWebhookOperationConfig = {
	url: string
	secret: string
	timeout: string
	retries: string
	headers: string
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
	'OCA\\ProfileFields\\Workflow\\EmailUserProfileFieldChangeOperation',
	'OCA\\ProfileFields\\Workflow\\NotifyAdminsOrGroupsProfileFieldChangeOperation',
	'OCA\\ProfileFields\\Workflow\\CreateTalkConversationProfileFieldChangeOperation',
	'OCA\\ProfileFields\\Workflow\\SendWebhookProfileFieldChangeOperation',
]
const workflowEntityClass = 'OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity'
const workflowUpdatedEventClass = 'OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent'
const workflowElementId = 'oca-profile-fields-check-user-profile-field'
const emailOperationElementId = 'oca-profile-fields-email-operation'
const targetsOperationElementId = 'oca-profile-fields-targets-operation'
const webhookOperationElementId = 'oca-profile-fields-webhook-operation'
const workflowOperationNames = new Set([
	t('profile_fields', 'Log profile field change'),
	t('profile_fields', 'Email affected user'),
	t('profile_fields', 'Notify admins or groups'),
	t('profile_fields', 'Create Talk conversation'),
	t('profile_fields', 'Send webhook'),
])
const workflowCardClassName = 'profile-fields-workflow-card'
const workflowItemClassName = 'profile-fields-workflow-item'
const workflowCardThemeStyleId = 'profile-fields-workflow-card-theme'

// TRANSLATORS The labels below are technical workflow operators shown in filter dropdowns.
// Keep them short to avoid truncation in compact UI layouts.
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

const parseJsonObject = (value: string): Record<string, unknown> | null => {
	const trimmedValue = value.trim()
	if (trimmedValue === '') {
		return null
	}

	try {
		const parsed = JSON.parse(trimmedValue) as unknown
		return parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed)
			? parsed as Record<string, unknown>
			: null
	} catch {
		return null
	}
}

const parseEmailOperationConfig = (value: string): WorkflowEmailOperationConfig => {
	const parsed = parseJsonObject(value)
	return {
		subjectTemplate: typeof parsed?.subjectTemplate === 'string' ? parsed.subjectTemplate : '',
		bodyTemplate: typeof parsed?.bodyTemplate === 'string' ? parsed.bodyTemplate : '',
	}
}

const serializeEmailOperationConfig = (config: WorkflowEmailOperationConfig): string => {
	if (config.subjectTemplate.trim() === '' && config.bodyTemplate.trim() === '') {
		return ''
	}

	return JSON.stringify({
		subjectTemplate: config.subjectTemplate,
		bodyTemplate: config.bodyTemplate,
	})
}

const parseTargetsOperationConfig = (value: string): WorkflowTargetsOperationConfig => {
	const parsed = parseJsonObject(value)
	return {
		targets: typeof parsed?.targets === 'string' ? parsed.targets : '',
	}
}

const serializeTargetsOperationConfig = (config: WorkflowTargetsOperationConfig): string => {
	const targets = config.targets
		.split(',')
		.map((target) => target.trim())
		.filter((target, index, items) => target !== '' && items.indexOf(target) === index)

	if (targets.length === 0) {
		return ''
	}

	return JSON.stringify({
		targets: targets.join(','),
	})
}

const getWorkflowTargetLabel = (token: string): string => {
	if (token === 'admin') {
		return t('profile_fields', 'Administrators')
	}

	if (token.startsWith('group:')) {
		return token.slice(6)
	}

	if (token.startsWith('user:')) {
		return token.slice(5)
	}

	return token
}

const getWorkflowTargetDescription = (token: string): string => {
	if (token === 'admin') {
		return t('profile_fields', 'All users in the admin group')
	}

	if (token.startsWith('group:')) {
		// TRANSLATORS {groupId} is the technical group identifier.
		return t('profile_fields', 'Group: {groupId}', { groupId: token.slice(6) })
	}

	if (token.startsWith('user:')) {
		// TRANSLATORS {userId} is the technical user identifier.
		return t('profile_fields', 'User: {userId}', { userId: token.slice(5) })
	}

	return t('profile_fields', 'Custom recipient token')
}

const getWorkflowTargetId = (token: string): string => {
	if (token === 'admin') {
		return 'special-admin'
	}

	if (token.startsWith('group:')) {
		return `group-${token.slice(6)}`
	}

	if (token.startsWith('user:')) {
		return `user-${token.slice(5)}`
	}

	return `custom-${token}`
}

const toWorkflowTargetOption = (
	token: string,
	label = getWorkflowTargetLabel(token),
	description = getWorkflowTargetDescription(token),
): WorkflowTargetOption => {
	if (token === 'admin') {
		return {
			id: getWorkflowTargetId(token),
			displayName: label,
			subname: description,
			isNoUser: true,
		}
	}

	if (token.startsWith('group:')) {
		return {
			id: getWorkflowTargetId(token),
			displayName: label,
			subname: description,
			isNoUser: true,
		}
	}

	if (token.startsWith('user:')) {
		const userId = token.slice(5)
		return {
			id: getWorkflowTargetId(token),
			displayName: label,
			subname: description,
			user: userId,
			isNoUser: false,
		}
	}

	return {
		id: getWorkflowTargetId(token),
		displayName: label,
		subname: description,
		isNoUser: true,
	}
}

const normalizeWorkflowTargetToken = (value: string): string | null => {
	const normalizedValue = value.trim().replace(/,$/, '')
	if (normalizedValue === '') {
		return null
	}

	if (normalizedValue === 'admin') {
		return normalizedValue
	}

	if (/^(group|user):[^,\s]+$/i.test(normalizedValue)) {
		const [scope, identifier] = normalizedValue.split(':', 2)
		return `${scope.toLowerCase()}:${identifier}`
	}

	return null
}

const parseWorkflowTargetTokens = (value: string): string[] => value
	.split(',')
	.map((token) => token.trim())
	.filter((token, index, items) => token !== '' && items.indexOf(token) === index)

const parseWebhookOperationConfig = (value: string): WorkflowWebhookOperationConfig => {
	if (/^https?:\/\//i.test(value.trim())) {
		return {
			url: value.trim(),
			secret: '',
			timeout: '',
			retries: '0',
			headers: '',
		}
	}

	const parsed = parseJsonObject(value)
	const headers = parsed?.headers !== null && typeof parsed?.headers === 'object'
		? Object.entries(parsed?.headers as Record<string, unknown>)
			.map(([name, headerValue]) => `${name}: ${String(headerValue)}`)
			.join('\n')
		: ''

	return {
		url: typeof parsed?.url === 'string' ? parsed.url : '',
		secret: typeof parsed?.secret === 'string' ? parsed.secret : '',
		timeout: typeof parsed?.timeout === 'number' || typeof parsed?.timeout === 'string' ? String(parsed.timeout) : '',
		retries: typeof parsed?.retries === 'number' || typeof parsed?.retries === 'string' ? String(parsed.retries) : '0',
		headers,
	}
}

const serializeWebhookOperationConfig = (config: WorkflowWebhookOperationConfig): string => {
	const headers = Object.fromEntries(config.headers
		.split(/\r?\n/)
		.map((line) => line.trim())
		.filter((line) => line.includes(':'))
		.map((line) => {
			const separatorIndex = line.indexOf(':')
			return [line.slice(0, separatorIndex).trim(), line.slice(separatorIndex + 1).trim()]
		}))

	if (config.secret.trim() === '' && config.timeout.trim() === '' && config.retries.trim() === '' && Object.keys(headers).length === 0) {
		return config.url.trim()
	}

	return JSON.stringify({
		url: config.url.trim(),
		secret: config.secret.trim(),
		timeout: config.timeout.trim() === '' ? undefined : Number(config.timeout),
		retries: config.retries.trim() === '' ? 0 : Number(config.retries),
		headers,
	})
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
			${workflowElementId} {
				display: flex;
				flex: 1 1 22rem;
				gap: .5rem;
				align-items: center;
				min-width: 0;
			}

			${workflowElementId} select,
			${workflowElementId} input {
				border: 1px solid var(--color-border-maxcontrast);
				border-radius: var(--border-radius-element, 6px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				font: inherit;
				padding: .45rem .6rem;
				min-height: 2.25rem;
			}

			${workflowElementId} select {
				flex: 1 1 14rem;
				min-width: 10rem;
			}

			${workflowElementId} input {
				flex: 1 1 10rem;
				min-width: 8rem;
			}

			${workflowElementId} input[hidden] {
				display: none;
			}

			${workflowElementId} input.invalid,
			${workflowElementId} select.invalid {
				border-color: var(--color-error);
			}
		`

		const fieldSelect = document.createElement('select')
		fieldSelect.disabled = this.disabledInternal || definitions.length === 0
		fieldSelect.className = selectedDefinition === null ? 'invalid' : ''

		const placeholder = document.createElement('option')
		placeholder.value = ''
		// TRANSLATORS "\u00A0" keeps the ellipsis attached to the previous word for correct typography and avoids awkward line breaks.
		placeholder.textContent = definitions.length === 0
			? t('profile_fields', 'Loading profile fields\u00A0…')
			: t('profile_fields', 'Choose a profile field')
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
			? t('profile_fields', 'Enter the numeric value to compare')
			: t('profile_fields', 'Enter the comparison value')
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
		const config = parseWebhookOperationConfig(this.modelValueInternal)
		const isUrlValid = /^https?:\/\/.+/i.test(config.url.trim())
		const retries = Number.parseInt(config.retries || '0', 10)
		const timeout = config.timeout.trim() === '' ? null : Number.parseInt(config.timeout, 10)
		const isValid = isUrlValid && (Number.isNaN(retries) || retries >= 0) && (timeout === null || timeout > 0)

		this.replaceChildren()

		const style = document.createElement('style')
		style.textContent = `
			${webhookOperationElementId} {
				display: grid;
				flex: 1 1 22rem;
				gap: .5rem;
				min-width: 0;
			}

			${webhookOperationElementId} input,
			${webhookOperationElementId} textarea {
				width: 100%;
				border: 1px solid var(--color-border-maxcontrast);
				border-radius: var(--border-radius-element, 6px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				font: inherit;
				padding: .45rem .6rem;
				min-height: 2.25rem;
			}

			${webhookOperationElementId} textarea {
				min-height: 5rem;
				resize: vertical;
			}

			${webhookOperationElementId} input.invalid,
			${webhookOperationElementId} textarea.invalid {
				border-color: var(--color-error);
			}
		`

		const urlInput = document.createElement('input')
		urlInput.type = 'url'
		urlInput.value = config.url
		urlInput.disabled = this.disabledInternal
		urlInput.placeholder = t('profile_fields', 'Webhook URL (https://…)')
		urlInput.className = config.url === '' || isUrlValid ? '' : 'invalid'

		const secretInput = document.createElement('input')
		secretInput.type = 'text'
		secretInput.value = config.secret
		secretInput.disabled = this.disabledInternal
		secretInput.placeholder = t('profile_fields', 'Optional shared secret (for request signing)')

		const timeoutInput = document.createElement('input')
		timeoutInput.type = 'number'
		timeoutInput.min = '1'
		timeoutInput.value = config.timeout
		timeoutInput.disabled = this.disabledInternal
		timeoutInput.placeholder = t('profile_fields', 'Timeout (seconds)')
		timeoutInput.className = timeout === null || timeout > 0 ? '' : 'invalid'

		const retriesInput = document.createElement('input')
		retriesInput.type = 'number'
		retriesInput.min = '0'
		retriesInput.value = config.retries
		retriesInput.disabled = this.disabledInternal
		retriesInput.placeholder = t('profile_fields', 'Retries (0 or more)')
		retriesInput.className = Number.isNaN(retries) || retries >= 0 ? '' : 'invalid'

		const headersInput = document.createElement('textarea')
		headersInput.value = config.headers
		headersInput.disabled = this.disabledInternal
		// TRANSLATORS "X-Key: value" is an example HTTP header in "name: value" format.
		headersInput.placeholder = t('profile_fields', 'Optional headers, one per line. Example: X-Key: value')

		const syncValue = () => {
			dispatchModelValue(this, serializeWebhookOperationConfig({
				url: urlInput.value,
				secret: secretInput.value,
				timeout: timeoutInput.value,
				retries: retriesInput.value,
				headers: headersInput.value,
			}))
		}

		for (const input of [urlInput, secretInput, timeoutInput, retriesInput, headersInput]) {
			input.addEventListener('input', syncValue)
		}

		this.append(style, urlInput, secretInput, timeoutInput, retriesInput, headersInput)
		dispatchValidity(this, isValid)
	}
}

class WorkflowEmailOperationElement extends HTMLElement {
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

	private syncFromAttributes(): void {
		this.modelValueInternal = this.getAttribute('model-value') ?? this.modelValueInternal
		this.disabledInternal = this.getAttribute('disabled') === '' || this.getAttribute('disabled') === 'true'
	}

	private render(): void {
		const config = parseEmailOperationConfig(this.modelValueInternal)
		const isValid = (config.subjectTemplate.trim() === '' && config.bodyTemplate.trim() === '')
			|| (config.subjectTemplate.trim() !== '' && config.bodyTemplate.trim() !== '')

		this.replaceChildren()

		const style = document.createElement('style')
		style.textContent = `
			${emailOperationElementId} {
				display: grid;
				flex: 1 1 22rem;
				gap: .5rem;
				min-width: 0;
			}

			${emailOperationElementId} input,
			${emailOperationElementId} textarea {
				width: 100%;
				border: 1px solid var(--color-border-maxcontrast);
				border-radius: var(--border-radius-element, 6px);
				background: var(--color-main-background);
				color: var(--color-main-text);
				font: inherit;
				padding: .45rem .6rem;
			}

			${emailOperationElementId} textarea {
				min-height: 7rem;
				resize: vertical;
			}

			${emailOperationElementId} .invalid {
				border-color: var(--color-error);
			}
		`

		const subjectInput = document.createElement('input')
		subjectInput.type = 'text'
		subjectInput.value = config.subjectTemplate
		subjectInput.disabled = this.disabledInternal
		subjectInput.placeholder = t('profile_fields', 'Optional email subject template')
		subjectInput.className = isValid || config.subjectTemplate.trim() !== '' ? '' : 'invalid'

		const bodyInput = document.createElement('textarea')
		bodyInput.value = config.bodyTemplate
		bodyInput.disabled = this.disabledInternal
		// TRANSLATORS Keep "{{fieldLabel}}" unchanged. It is a runtime placeholder replaced with the field label.
		bodyInput.placeholder = t('profile_fields', 'Optional email body template. You can use placeholders like {{fieldLabel}}')
		bodyInput.className = isValid || config.bodyTemplate.trim() !== '' ? '' : 'invalid'

		const syncValue = () => {
			dispatchModelValue(this, serializeEmailOperationConfig({
				subjectTemplate: subjectInput.value,
				bodyTemplate: bodyInput.value,
			}))
		}

		subjectInput.addEventListener('input', syncValue)
		bodyInput.addEventListener('input', syncValue)

		this.append(style, subjectInput, bodyInput)
		dispatchValidity(this, isValid)
	}
}

class WorkflowTargetsOperationElement extends HTMLElement {
	private modelValueInternal = ''
	private disabledInternal = false
	private queryInternal = ''
	private suggestionsInternal: WorkflowTargetOption[] = []
	private isLoadingInternal = false
	private searchTimeoutInternal: number | null = null
	private searchSequenceInternal = 0
	private knownOptionsInternal = new Map<string, WorkflowTargetOption>()
	private knownTokensByIdInternal = new Map<string, string>()
	private appInternal: App<Element> | null = null
	private mountPointInternal: HTMLDivElement | null = null
	private viewStateInternal = reactive<WorkflowTargetsViewState>({
		disabled: false,
		loading: false,
		options: [],
		selected: [],
	})

	static get observedAttributes(): string[] {
		return ['model-value', 'disabled']
	}

	connectedCallback(): void {
		this.syncFromAttributes()
		this.ensureApp()
		this.syncViewState()
	}

	disconnectedCallback(): void {
		if (this.searchTimeoutInternal !== null) {
			window.clearTimeout(this.searchTimeoutInternal)
			this.searchTimeoutInternal = null
		}

		this.appInternal?.unmount()
		this.appInternal = null
		this.mountPointInternal = null
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

		this.syncViewState()
	}

	private syncFromAttributes(): void {
		this.modelValueInternal = this.getAttribute('model-value') ?? this.modelValueInternal
		this.disabledInternal = this.getAttribute('disabled') === '' || this.getAttribute('disabled') === 'true'
	}

	private get adminOption(): WorkflowTargetOption {
		return toWorkflowTargetOption(
			'admin',
			t('profile_fields', 'Administrators'),
			t('profile_fields', 'All users in the admin group'),
		)
	}

	private get selectedTargets(): string[] {
		return parseWorkflowTargetTokens(parseTargetsOperationConfig(this.modelValueInternal).targets)
	}

	private getTargetOption(token: string): WorkflowTargetOption {
		const existingOption = this.knownOptionsInternal.get(token)
		if (existingOption !== undefined) {
			return existingOption
		}

		return this.registerTargetOption(token, toWorkflowTargetOption(token))
	}

	private registerTargetOption(token: string, option: WorkflowTargetOption): WorkflowTargetOption {
		this.knownOptionsInternal.set(token, option)
		this.knownTokensByIdInternal.set(option.id, token)
		return option
	}

	private getTokenForOption(option: WorkflowTargetOption): string | null {
		return this.knownTokensByIdInternal.get(option.id) ?? null
	}

	private get visibleOptions(): WorkflowTargetOption[] {
		const selectedTargets = new Set(this.selectedTargets)

		if (this.queryInternal.trim() === '') {
			return selectedTargets.has('admin') ? [] : [this.adminOption]
		}

		return this.suggestionsInternal.filter((option) => {
			const token = this.getTokenForOption(option)
			return token === null || !selectedTargets.has(token)
		})
	}

	private ensureApp(): void {
		if (this.appInternal !== null || !this.isConnected) {
			return
		}

		this.replaceChildren()

		const style = document.createElement('style')
		style.textContent = `
			${targetsOperationElementId} {
				display: grid;
				flex: 1 1 22rem;
				gap: .5rem;
				min-width: 0;
			}
		`

		this.mountPointInternal = document.createElement('div')
		this.append(style, this.mountPointInternal)

		this.appInternal = createApp({
			name: 'WorkflowTargetsOperationSelect',
			render: () => h(WorkflowTargetsSelect, {
					modelValue: this.viewStateInternal.selected,
					disabled: this.viewStateInternal.disabled,
					options: this.viewStateInternal.options,
					inputLabel: t('profile_fields', 'Search for admins, groups, or users'),
					// TRANSLATORS "administrators" refers to users in the built-in admin group.
					helperText: t('profile_fields', 'Select who should be notified. If empty, administrators are notified by default.'),
					loading: this.viewStateInternal.loading,
					onSearch: (value: string) => this.handleSearch(value),
					'onUpdate:modelValue': (value: unknown) => this.handleSelection(Array.isArray(value)
						? value as WorkflowTargetOption[]
						: [value as WorkflowTargetOption]),
				}),
		})
		this.appInternal.mount(this.mountPointInternal)
	}

	private syncViewState(): void {
		this.ensureApp()
		this.registerTargetOption('admin', this.adminOption)
		this.viewStateInternal.disabled = this.disabledInternal
		this.viewStateInternal.loading = this.isLoadingInternal
		this.viewStateInternal.selected = this.selectedTargets.map((token) => this.getTargetOption(token))
		this.viewStateInternal.options = this.visibleOptions
		dispatchValidity(this, true)
	}

	private updateTargets(targets: string[]): void {
		this.viewStateInternal.selected = targets.map((token) => this.getTargetOption(token))
		this.viewStateInternal.options = this.visibleOptions
		dispatchModelValue(this, serializeTargetsOperationConfig({ targets: targets.join(',') }))
		dispatchValidity(this, true)
	}

	private handleSelection(options: WorkflowTargetOption[]): void {
		this.queryInternal = ''
		this.suggestionsInternal = []
		this.isLoadingInternal = false

		const normalizedTargets = options
			.map((option) => this.getTokenForOption(option))
			.filter((token): token is string => token !== null)
			.map((token) => normalizeWorkflowTargetToken(token) ?? token)
			.filter((token, index, items) => items.indexOf(token) === index)

		this.updateTargets(normalizedTargets)
	}

	private handleSearch(value: string): void {
		this.queryInternal = value
		this.viewStateInternal.options = this.visibleOptions
		this.scheduleSearch()
	}

	private scheduleSearch(): void {
		if (this.searchTimeoutInternal !== null) {
			window.clearTimeout(this.searchTimeoutInternal)
		}

		this.searchTimeoutInternal = window.setTimeout(() => {
			void this.loadSuggestions()
		}, 150)
	}

	private async loadSuggestions(): Promise<void> {
		const query = this.queryInternal.trim()
		const sequence = ++this.searchSequenceInternal

		if (query === '') {
			this.isLoadingInternal = false
			this.suggestionsInternal = []
			this.syncViewState()
			return
		}

		this.isLoadingInternal = true
		this.syncViewState()
		try {
			const suggestions = await searchWorkflowTargetSuggestions(query)
			if (sequence !== this.searchSequenceInternal) {
				return
			}

			const optionMap = new Map<string, WorkflowTargetOption>()
			if ('administrators'.includes(query.toLowerCase()) || 'admin'.includes(query.toLowerCase())) {
				optionMap.set('admin', this.registerTargetOption('admin', this.adminOption))
			}

			for (const suggestion of suggestions) {
				const option = toWorkflowTargetOption(suggestion.token, suggestion.label, suggestion.description)
				optionMap.set(suggestion.token, this.registerTargetOption(suggestion.token, option))
			}

			this.suggestionsInternal = Array.from(optionMap.values())
		} catch {
			if (sequence !== this.searchSequenceInternal) {
				return
			}

			this.suggestionsInternal = 'administrators'.includes(query.toLowerCase()) || 'admin'.includes(query.toLowerCase())
				? [this.adminOption]
				: []
		} finally {
			if (sequence === this.searchSequenceInternal) {
				this.isLoadingInternal = false
				this.syncViewState()
			}
		}
	}
}

if (!window.customElements.get(workflowElementId)) {
	window.customElements.define(workflowElementId, WorkflowProfileFieldElement)
}

if (!window.customElements.get(emailOperationElementId)) {
	window.customElements.define(emailOperationElementId, WorkflowEmailOperationElement)
}

if (!window.customElements.get(targetsOperationElementId)) {
	window.customElements.define(targetsOperationElementId, WorkflowTargetsOperationElement)
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
		id: 'OCA\\ProfileFields\\Workflow\\EmailUserProfileFieldChangeOperation',
		operation: '',
		color: 'var(--color-success)',
		element: emailOperationElementId,
	},
	{
		id: 'OCA\\ProfileFields\\Workflow\\NotifyAdminsOrGroupsProfileFieldChangeOperation',
		operation: '',
		color: 'var(--color-success)',
		element: targetsOperationElementId,
	},
	{
		id: 'OCA\\ProfileFields\\Workflow\\CreateTalkConversationProfileFieldChangeOperation',
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
