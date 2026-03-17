<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\Workflow\Event\AbstractProfileFieldValueEvent;
use OCP\EventDispatcher\Event;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;
use Psr\Log\LoggerInterface;

class LogProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private LoggerInterface $logger,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Log profile field change');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Write a server log entry when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/history.svg');
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
			foreach ($matches as $match) {
				$this->logger->info('Profile field workflow rule matched', [
					'app' => 'profile_fields',
					'rule_id' => $match['id'] ?? null,
					'rule_name' => $match['name'] ?? null,
					'field_key' => $subject->getFieldDefinition()->getFieldKey(),
					'user_uid' => $subject->getUserUid(),
					'actor_uid' => $subject->getActorUid(),
					'previous_value' => $subject->getPreviousValue(),
					'current_value' => $subject->getCurrentValue(),
					'previous_visibility' => $subject->getPreviousVisibility(),
					'current_visibility' => $subject->getCurrentVisibility(),
					'event_name' => $eventName,
				]);
			}
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}
}
