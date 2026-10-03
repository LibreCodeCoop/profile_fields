// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
// SPDX-License-Identifier: AGPL-3.0-or-later

import { describe, expect, it, vi } from 'vitest'

import {
	applyDefaultTriggerToNewestRule,
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

const createStore = (rules: WorkflowEngineRule[]): WorkflowEngineStore => ({
	getEntities: () => entities,
	getRules: () => rules,
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
		let subscriber: ((mutation: { type: string }) => void) | undefined
		const vuexStore = {
			state: { rules: [rule], entities },
			commit: vi.fn(),
			subscribe: vi.fn((handler: (mutation: { type: string }) => void) => {
				subscriber = handler
			}),
		}
		const root = Object.assign(document.createElement('div'), { __vue__: { $store: vuexStore } })

		const store = getWorkflowEngineStore(root)
		const callback = vi.fn()
		store?.onRuleCreated(callback)
		subscriber?.({ type: 'updateRule' })
		subscriber?.({ type: 'addRule' })
		store?.setRuleTrigger(rule, entityClass, [eventClass])

		expect(store?.getEntities()).toBe(entities)
		expect(store?.getRules()).toEqual([rule])
		expect(callback).toHaveBeenCalledTimes(1)
		expect(vuexStore.commit).toHaveBeenCalledWith('updateRule', { ...rule, entity: entityClass, events: [eventClass] })
	})

	it('adapts the Pinia store of the Vue 3 workflow engine', () => {
		const rule = createRule()
		let actionHandler: ((context: { name: string, after: (callback: () => void) => void }) => void) | undefined
		const piniaStore = {
			entities,
			rules: [rule],
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
		expect(store?.getRules()).toEqual([rule])
		expect(callback).toHaveBeenCalledTimes(1)
		expect(piniaStore.setRuleTrigger).toHaveBeenCalledWith(rule, entityClass, [eventClass])
	})

	it('ignores a Pinia instance without the workflow engine store', () => {
		const root = Object.assign(document.createElement('div'), {
			__vue_app__: { config: { globalProperties: { $pinia: { _s: new Map([['other', {}]]) } } } },
		})

		expect(getWorkflowEngineStore(root)).toBeNull()
	})
})

describe('applyDefaultTriggerToNewestRule', () => {
	it('points the newest unsaved profile field rule at the profile field update event', () => {
		const olderRule = createRule({ id: -2 })
		const newestRule = createRule({ id: -1 })
		const store = createStore([createRule({ id: 5 }), olderRule, newestRule])

		applyDefaultTriggerToNewestRule(store, defaults)

		expect(store.setRuleTrigger).toHaveBeenCalledOnce()
		expect(store.setRuleTrigger).toHaveBeenCalledWith(newestRule, entityClass, [eventClass])
	})

	it('ignores saved rules and rules of other operations', () => {
		const store = createStore([
			createRule({ id: 3 }),
			createRule({ id: -1, class: 'OCA\\FilesAccessControl\\Operation' }),
		])

		applyDefaultTriggerToNewestRule(store, defaults)

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})

	it('keeps a rule that already uses the default trigger', () => {
		const store = createStore([createRule({ entity: entityClass, events: [eventClass] })])

		applyDefaultTriggerToNewestRule(store, defaults)

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})

	it('falls back to the first event of the entity', () => {
		const rule = createRule()
		const store = createStore([rule])

		applyDefaultTriggerToNewestRule(store, { ...defaults, eventClass: 'missingEvent' })

		expect(store.setRuleTrigger).toHaveBeenCalledWith(rule, entityClass, ['otherEvent'])
	})

	it('does nothing when the profile field entity is not available', () => {
		const store = createStore([createRule()])

		applyDefaultTriggerToNewestRule(store, { ...defaults, entityClass: 'missingEntity' })

		expect(store.setRuleTrigger).not.toHaveBeenCalled()
	})
})
