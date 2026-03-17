<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Workflow\Event\AbstractProfileFieldValueEvent;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class NotifyUserProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private INotificationManager $notificationManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Notify affected user');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Send an internal notification to the affected user when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/profile.svg');
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
		if (trim($operation) !== '') {
			throw new \UnexpectedValueException($this->l10n->t('This workflow operation does not accept custom configuration'));
		}
	}

	#[\Override]
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if (!$event instanceof AbstractProfileFieldValueEvent) {
			return;
		}

		try {
			$matches = $ruleMatcher->getFlows(false);
			if ($matches === []) {
				return;
			}

			$subject = $event->getWorkflowSubject();
			$firstMatch = $matches[0];
			$fieldDefinition = $subject->getFieldDefinition();
			$fieldLabel = trim($fieldDefinition->getLabel()) !== '' ? $fieldDefinition->getLabel() : $fieldDefinition->getFieldKey();

			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp(Application::APP_ID)
				->setUser($subject->getUserUid())
				->setObject('profile-field-change', sprintf('%s:%s:%s', (string)($firstMatch['id'] ?? 'workflow'), $subject->getUserUid(), $fieldDefinition->getFieldKey()))
				->setSubject('profile-field-change-notification', [
					'fieldLabel' => $fieldLabel,
					'actorUid' => $subject->getActorUid(),
				])
				->setDateTime(new \DateTime())
				->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/profile.svg')));

			$this->notificationManager->notify($notification);
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}
}
