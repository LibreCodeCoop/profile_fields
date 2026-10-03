// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'

import {
	applyDefaultTriggerToCreatedRule,
	getWorkflowEngineStore,
	type WorkflowEngineRule,
	type WorkflowEngineStore,
} from '../../utils/workflowEngineStore.ts'

const entityClass = 'OCA\\ProfileFields\\Workflow\\ProfileFieldValueEntity'
const eventClass = 'OCA\\ProfileFields\\Workflow\\Event\\ProfileFieldValueUpdatedEvent'
const operationClass = 'OCA\\ProfileFields\\Workflow\\LogProfileFieldChangeOperation'
const fileEntityClass = 'OCA\\WorkflowEngine\\Entity\\File'

const defaults = {
	entityClass,
	eventClass,
	operationClasses: [operationClass],
}

const entities = [
	{ id: fileEntityClass, events: [{ eventName: 'postCreate', displayName: 'File created' }] },
	{
		id: entityClass,
		events: [
			{ eventName: 'otherEvent', displayName: 'Other' },
			{ eventName: eventClass, displayName: 'Profile field value updated' },
		],
	},
]

const createRule = (overrides: Partial<WorkflowEngineRule> = {}): WorkflowEngineRule => ({
	id: -1,
	class: operationClass,
	entity: fileEntityClass,
	events: ['postCreate'],
	...overrides,
})

const createStore = (): WorkflowEngineStore => ({
	getEntities: () => entities,
	setRuleTrigger: vi.fn(),
	onRuleCreated: vi.fn(),
})

describe('getWorkflowEngineStore', () => {
	it('returns null without a workflow engine root', () => {
		expect(getWorkflowEngineStore(null)).toBeNull()
	})

	it('returns null when the root has no mounted store', () => {
		expect(getWorkflowEngineStore(document.createElement('div'))).toBeNull()
	})

	it('adapts the Vuex store of the Vue 2 workflow engine', () => {
		const rule = createRule()
		const state = { rules: [createRule({ id: 7 }), rule], entities }
		let subscriber: ((mutation: { type: string }, mutationState: typeof state) => void) | undefined
		const vuexStore = {
			state,
			commit: vi.fn(),
			subscribe: vi.fn((handler: (mutation: { type: string }, mutationState: typeof state) => void) => {
				subscriber = handler
			}),
		}
		const root = Object.assign(document.createElement('div'), { __vue__: { $store: vuexStore } })

		const store = getWorkflowEngineStore(root)
		const callback = vi.fn()
		store?.onRuleCreated(callback)
		subscriber?.({ type: 'updateRule' }, state)
		subscriber?.({ type: 'addRule' }, state)
		store?.setRuleTrigger(rule, entityClass, [eventClass])

		expect(store?.getEntities()).toBe(entities)
		expect(callback).toHaveBeenCalledOnce()
		expect(callback).toHaveBeenCalledWith(rule)
		expect(vuexStore.commit).toHaveBeenCalledWith('updateRule', { ...rule, entity: entityClass, events: [eventClass] })
	})

	it('adapts the Pinia store of the Vue 3 workflow engine', () => {
		const rule = createRule()
		let actionHandler: ((context: { name: string, after: (callback: () => void) => void }) => void) | undefined
		const piniaStore = {
			entities,
			rules: [createRule({ id: 7 }), rule],
			setRuleTrigger: vi.fn(),
			$onAction: vi.fn((handler: (context: { name: string, after: (callback: () => void) => void }) => void) => {
				actionHandler = handler
			}),
		}
		const root = Object.assign(document.createElement('div'), {
			__vue_app__: { config: { globalProperties: { $pinia: { _s: new Map([['workflowengine', piniaStore]]) } } } },
		})

		const store = getWorkflowEngineStore(root)
		const callback = vi.fn()
		store?.onRuleCreated(callback)
		actionHandler?.({ name: 'updateRule', after: (afterCallback) => afterCallback() })
		actionHandler?.({ name: 'createNewRule', after: (afterCallback) => afterCallback() })
		store?.setRuleTrigger(rule, entityClass, [eventClass])

		expect(store?.getEntities()).toBe(entities)
		expect(callback).toHaveBeenCalledOnce()
		expect(callback).toHaveBeenCalledWith(rule)
		expect(piniaStore.setRuleTrigger).toHaveBeenCalledWith(rule, entityClass, [eventClass])
	})

	it('ignores a Pinia instance without the workflow engine store', () => {
		const root = Object.assign(document.createElement('div'), {
			__vue_app__: { config: { globalProperties: { $pinia: { _s: new Map([['other', {}]]) } } } },
		})

		expect(getWorkflowEngineStore(root)).toBeNull()
	})
})

describe('applyDefaultTriggerToCreatedRule', () => {
	it('points a new profile field rule at the profile field update event', () => {
		const rule = createRule()
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, rule, defaults)

		expect(store.setRuleTrigger).toHaveBeenCalledOnce()
		expect(store.setRuleTrigger).toHaveBeenCalledWith(rule, entityClass, [eventClass])
	})

	it('ignores rules of other operations so profile field drafts keep their trigger', () => {
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, createRule({ class: 'OCA\\FilesAccessControl\\Operation' }), defaults)

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})

	it('ignores saved rules', () => {
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, createRule({ id: 3 }), defaults)

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})

	it('keeps a rule that already uses the default trigger', () => {
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, createRule({ entity: entityClass, events: [eventClass] }), defaults)

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})

	it('falls back to the first event of the entity', () => {
		const rule = createRule()
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, rule, { ...defaults, eventClass: 'missingEvent' })

		expect(store.setRuleTrigger).toHaveBeenCalledWith(rule, entityClass, ['otherEvent'])
	})

	it('does nothing when the profile field entity is not available', () => {
		const store = createStore()

		applyDefaultTriggerToCreatedRule(store, createRule(), { ...defaults, entityClass: 'missingEntity' })

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})
})
