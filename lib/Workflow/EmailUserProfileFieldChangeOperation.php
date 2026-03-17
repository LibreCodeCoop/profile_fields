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
		if ($this->parseConfig($operation) === null) {
			throw new \UnexpectedValueException($this->l10n->t('A valid email template configuration is required'));
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
			$config = $this->parseConfig((string)($matches[0]['operation'] ?? '')) ?? $this->defaultConfig();
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
				->setSubject($this->renderTemplate($config['subjectTemplate'], $subject->getActorUid(), $subject->getUserUid(), $fieldDefinition->getFieldKey(), $fieldLabel, $subject->getPreviousValue(), $subject->getCurrentValue(), $subject->getPreviousVisibility(), $subject->getCurrentVisibility()))
				->setPlainBody($this->renderTemplate($config['bodyTemplate'], $subject->getActorUid(), $subject->getUserUid(), $fieldDefinition->getFieldKey(), $fieldLabel, $subject->getPreviousValue(), $subject->getCurrentValue(), $subject->getPreviousVisibility(), $subject->getCurrentVisibility()));

			$this->mailer->send($message);
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}

	private function normalizeValue(?string $value): string {
		$normalized = trim((string)$value);
		return $normalized !== '' ? $normalized : $this->l10n->t('(empty)');
	}

	/**
	 * @return array{subjectTemplate: string, bodyTemplate: string}|null
	 */
	private function parseConfig(string $operation): ?array {
		$config = trim($operation);
		if ($config === '') {
			return $this->defaultConfig();
		}

		try {
			$decoded = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}

		if (!is_array($decoded)) {
			return null;
		}

		$subjectTemplate = trim((string)($decoded['subjectTemplate'] ?? ''));
		$bodyTemplate = trim((string)($decoded['bodyTemplate'] ?? ''));
		if ($subjectTemplate === '' || $bodyTemplate === '') {
			return null;
		}

		return [
			'subjectTemplate' => $subjectTemplate,
			'bodyTemplate' => $bodyTemplate,
		];
	}

	/**
	 * @return array{subjectTemplate: string, bodyTemplate: string}
	 */
	private function defaultConfig(): array {
		return [
			'subjectTemplate' => 'Your profile field was updated',
			'bodyTemplate' => 'Your profile field "{{fieldLabel}}" was updated by {{actorUid}}.' . "\n\n" . 'Previous value: {{previousValue}}' . "\n" . 'Current value: {{currentValue}}' . "\n" . 'Previous visibility: {{previousVisibility}}' . "\n" . 'Current visibility: {{currentVisibility}}',
		];
	}

	private function renderTemplate(string $template, string $actorUid, string $userUid, string $fieldKey, string $fieldLabel, ?string $previousValue, ?string $currentValue, ?string $previousVisibility, ?string $currentVisibility): string {
		return strtr($template, [
			'{{actorUid}}' => $actorUid,
			'{{userUid}}' => $userUid,
			'{{fieldKey}}' => $fieldKey,
			'{{fieldLabel}}' => $fieldLabel,
			'{{previousValue}}' => $this->normalizeValue($previousValue),
			'{{currentValue}}' => $this->normalizeValue($currentValue),
			'{{previousVisibility}}' => $this->normalizeValue($previousVisibility),
			'{{currentVisibility}}' => $this->normalizeValue($currentVisibility),
		]);
	}
}
