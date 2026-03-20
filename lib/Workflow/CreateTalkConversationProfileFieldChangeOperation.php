<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\Workflow\Event\AbstractProfileFieldValueEvent;
use OCP\EventDispatcher\Event;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Talk\IBroker;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class CreateTalkConversationProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private IBroker $broker,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Create Talk conversation');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Create a Talk conversation with the affected user and administrators when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/comment.svg');
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
		if (trim($operation) !== '') {
			throw new \UnexpectedValueException($this->l10n->t('This workflow operation does not support custom configuration.'));
		}
	}

	#[\Override]
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
		if (!$event instanceof AbstractProfileFieldValueEvent) {
			return;
		}

		try {
			$matches = $ruleMatcher->getFlows(false);
			if ($matches === [] || !$this->broker->hasBackend()) {
				return;
			}

			$subject = $event->getWorkflowSubject();
			$fieldDefinition = $subject->getFieldDefinition();
			$fieldLabel = trim($fieldDefinition->getLabel()) !== '' ? $fieldDefinition->getLabel() : $fieldDefinition->getFieldKey();
			$moderators = [];

			$affectedUser = $this->userManager->get($subject->getUserUid());
			if ($affectedUser instanceof IUser) {
				$moderators[$affectedUser->getUID()] = $affectedUser;
			}

			foreach ($this->groupManager->get('admin')?->getUsers() ?? [] as $adminUser) {
				$moderators[$adminUser->getUID()] = $adminUser;
			}

			if ($moderators === []) {
				return;
			}

			$this->broker->createConversation(
				// TRANSLATORS %1$s is the profile field label, %2$s is the affected user ID.
				$this->l10n->t('Profile field changed: %1$s for user %2$s', [
					$fieldLabel,
					$subject->getUserUid(),
				]),
				array_values($moderators),
				null,
			);
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}
}
