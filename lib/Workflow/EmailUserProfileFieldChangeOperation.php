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
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class EmailUserProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private IMailer $mailer,
		private IUserManager $userManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Email affected user');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Send an email to the affected user when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/mail.svg');
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
			$user = $this->userManager->get($subject->getUserUid());
			if ($user === null) {
				return;
			}

			$emailAddress = trim((string)$user->getEMailAddress());
			if ($emailAddress === '') {
				return;
			}

			$displayName = trim((string)$user->getDisplayName());
			if ($displayName === '') {
				$displayName = $subject->getUserUid();
			}

			$fieldDefinition = $subject->getFieldDefinition();
			$fieldLabel = trim($fieldDefinition->getLabel()) !== '' ? $fieldDefinition->getLabel() : $fieldDefinition->getFieldKey();

			$message = $this->mailer->createMessage();
			$message
				->setTo([$emailAddress => $displayName])
				->setSubject($this->l10n->t('Your profile field was updated'))
				->setPlainBody(sprintf(
					$this->l10n->t('Your profile field "%1$s" was updated by %2$s.' . "\n\n" . 'Previous value: %3$s' . "\n" . 'Current value: %4$s' . "\n" . 'Previous visibility: %5$s' . "\n" . 'Current visibility: %6$s'),
					$fieldLabel,
					$subject->getActorUid(),
					$this->normalizeValue($subject->getPreviousValue()),
					$this->normalizeValue($subject->getCurrentValue()),
					$this->normalizeValue($subject->getPreviousVisibility()),
					$this->normalizeValue($subject->getCurrentVisibility()),
				));

			$this->mailer->send($message);
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}

	private function normalizeValue(?string $value): string {
		$normalized = trim((string)$value);
		return $normalized !== '' ? $normalized : $this->l10n->t('(empty)');
	}
}
