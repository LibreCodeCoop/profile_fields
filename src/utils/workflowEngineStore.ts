// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

export type WorkflowEngineEntity = {
	id: string
	events: Array<{
		eventName: string
		displayName: string
	}>
}

export type WorkflowEngineRule = {
	id: number
	class: string
	entity: string
	events: string[]
}

export type WorkflowEngineStore = {
	getEntities: () => WorkflowEngineEntity[]
	setRuleTrigger: (rule: WorkflowEngineRule, entity: string, events: string[]) => void
	onRuleCreated: (callback: (rule: WorkflowEngineRule) => void) => void
}

export type WorkflowRuleDefaults = {
	entityClass: string
	eventClass: string
	operationClasses: string[]
}

type VuexWorkflowStore = {
	state: {
		rules: WorkflowEngineRule[]
		entities: WorkflowEngineEntity[]
	}
	commit: (type: string, payload?: unknown) => void
	subscribe: (handler: (mutation: { type: string }, state: VuexWorkflowStore['state']) => void) => unknown
}

type PiniaActionContext = {
	name: string
	after: (callback: () => void) => void
}

type PiniaWorkflowStore = {
	entities: WorkflowEngineEntity[]
	rules: WorkflowEngineRule[]
	setRuleTrigger: (rule: WorkflowEngineRule, entity: string, events: string[]) => void
	$onAction: (handler: (context: PiniaActionContext) => void) => unknown
}

type WorkflowEngineRootElement = Element & {
	__vue__?: { $store?: VuexWorkflowStore }
	__vue_app__?: { config?: { globalProperties?: { $pinia?: { _s?: Map<string, unknown> } } } }
}

const piniaStoreId = 'workflowengine'

const createVuexAdapter = (store: VuexWorkflowStore): WorkflowEngineStore => ({
	getEntities: () => store.state.entities,
	setRuleTrigger: (rule, entity, events) => store.commit('updateRule', { ...rule, entity, events }),
	onRuleCreated: (callback) => {
		store.subscribe((mutation, state) => {
			const createdRule = state.rules.at(-1)
			if (mutation.type === 'addRule' && createdRule !== undefined) {
				callback(createdRule)
			}
		})
	},
})

const createPiniaAdapter = (store: PiniaWorkflowStore): WorkflowEngineStore => ({
	getEntities: () => store.entities,
	setRuleTrigger: (rule, entity, events) => store.setRuleTrigger(rule, entity, events),
	onRuleCreated: (callback) => {
		store.$onAction(({ name, after }) => {
			if (name !== 'createNewRule') {
				return
			}

			after(() => {
				const createdRule = store.rules.at(-1)
				if (createdRule !== undefined) {
					callback(createdRule)
				}
			})
		})
	},
})

const isPiniaWorkflowStore = (store: unknown): store is PiniaWorkflowStore => {
	return typeof store === 'object'
		&& store !== null
		&& typeof (store as PiniaWorkflowStore).$onAction === 'function'
		&& typeof (store as PiniaWorkflowStore).setRuleTrigger === 'function'
}

export const getWorkflowEngineStore = (root: Element | null): WorkflowEngineStore | null => {
	if (root === null) {
		return null
	}

	const workflowRoot = root as WorkflowEngineRootElement
	const vuexStore = workflowRoot.__vue__?.$store
	if (vuexStore !== undefined) {
		return createVuexAdapter(vuexStore)
	}

	const piniaStore = workflowRoot.__vue_app__?.config?.globalProperties?.$pinia?._s?.get(piniaStoreId)
	if (isPiniaWorkflowStore(piniaStore)) {
		return createPiniaAdapter(piniaStore)
	}

	return null
}

const getDefaultEventName = (store: WorkflowEngineStore, defaults: WorkflowRuleDefaults): string | null => {
	const entity = store.getEntities().find((item) => item.id === defaults.entityClass)
	if (entity === undefined) {
		return null
	}

	return entity.events.find((event) => event.eventName === defaults.eventClass)?.eventName
		?? entity.events[0]?.eventName
		?? null
}

export const applyDefaultTriggerToCreatedRule = (store: WorkflowEngineStore, rule: WorkflowEngineRule, defaults: WorkflowRuleDefaults): void => {
	if (!defaults.operationClasses.includes(rule.class) || rule.id >= 0) {
		return
	}

	const defaultEventName = getDefaultEventName(store, defaults)
	if (defaultEventName === null) {
		return
	}

	if (rule.entity === defaults.entityClass && rule.events.length === 1 && rule.events[0] === defaultEventName) {
		return
	}

	store.setRuleTrigger(rule, defaults.entityClass, [defaultEventName])
}
