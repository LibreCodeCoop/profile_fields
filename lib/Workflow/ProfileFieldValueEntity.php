<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\Workflow\Event\AbstractProfileFieldValueEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueCreatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent;
use OCA\ProfileFields\Workflow\Event\ProfileFieldVisibilityUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\GenericEntityEvent;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IRuleMatcher;

class ProfileFieldValueEntity implements IEntity {
	private ?ProfileFieldValueWorkflowSubject $workflowSubject = null;

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Profile field value');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/profile.svg');
	}

	#[\Override]
	public function getEvents(): array {
		return [
			new GenericEntityEvent($this->l10n->t('Profile field value created'), ProfileFieldValueCreatedEvent::class),
			new GenericEntityEvent($this->l10n->t('Profile field value updated'), ProfileFieldValueUpdatedEvent::class),
			new GenericEntityEvent($this->l10n->t('Profile field visibility updated'), ProfileFieldVisibilityUpdatedEvent::class),
		];
	}

	#[\Override]
	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void {
		if (!$event instanceof AbstractProfileFieldValueEvent) {
			return;
		}

		$this->workflowSubject = $event->getWorkflowSubject();
		$this->workflowSubjectContext->set($this->workflowSubject);
		$ruleMatcher->setEntitySubject($this, $this->workflowSubject);
	}

	#[\Override]
	public function isLegitimatedForUserId(string $userId): bool {
		return $this->workflowSubject?->getUserUid() === $userId;
	}
}
