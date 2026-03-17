<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Workflow\Event\AbstractProfileFieldValueEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class CreateActivityProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private IActivityManager $activityManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Create activity entry');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Publish an activity stream entry when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/recent.svg');
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
			$fieldDefinition = $subject->getFieldDefinition();
			$fieldLabel = trim($fieldDefinition->getLabel()) !== '' ? $fieldDefinition->getLabel() : $fieldDefinition->getFieldKey();
			$firstMatch = $matches[0];

			$activity = $this->activityManager->generateEvent();
			$activity
				->setApp(Application::APP_ID)
				->setType('profile_fields')
				->setAffectedUser($subject->getUserUid())
				->setAuthor($subject->getActorUid())
				->setSubject('profile-field-change-activity', [
					'fieldLabel' => $fieldLabel,
					'actorUid' => $subject->getActorUid(),
				])
				->setParsedSubject($this->l10n->t('Profile field change recorded'))
				->setParsedMessage(sprintf(
					$this->l10n->t('%1$s changed the %2$s profile field from %3$s to %4$s.'),
					$subject->getActorUid(),
					$fieldLabel,
					$this->normalizeValue($subject->getPreviousValue()),
					$this->normalizeValue($subject->getCurrentValue()),
				))
				->setObject('profile-field', sprintf('%s:%s:%s', (string)($firstMatch['id'] ?? 'workflow'), $subject->getUserUid(), $fieldDefinition->getFieldKey()), $fieldLabel)
				->setTimestamp(time())
				->setIcon($this->urlGenerator->getAbsoluteURL($this->getIcon()));

			$this->activityManager->publish($activity);
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}

	private function normalizeValue(?string $value): string {
		$normalized = trim((string)$value);
		return $normalized !== '' ? $normalized : $this->l10n->t('(empty)');
	}
}
