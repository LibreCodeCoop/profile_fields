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
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class SendWebhookProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private IClientService $clientService,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Send webhook');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Send a webhook request when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/share.svg');
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
		if (!$this->isValidWebhookUrl($operation)) {
			throw new \UnexpectedValueException($this->l10n->t('A valid HTTP or HTTPS webhook URL is required'));
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

			foreach ($matches as $match) {
				$webhookUrl = trim((string)($match['operation'] ?? ''));
				if (!$this->isValidWebhookUrl($webhookUrl)) {
					continue;
				}

				$this->clientService->newClient()->post($webhookUrl, [
					'headers' => [
						'Content-Type' => 'application/json',
						'Accept' => 'application/json',
					],
					'body' => json_encode([
						'app' => Application::APP_ID,
						'event' => [
							'name' => $eventName,
							'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
						],
						'rule' => [
							'id' => $match['id'] ?? null,
							'name' => $match['name'] ?? null,
						],
						'user' => [
							'uid' => $subject->getUserUid(),
						],
						'actor' => [
							'uid' => $subject->getActorUid(),
						],
						'field' => [
							'key' => $fieldDefinition->getFieldKey(),
							'label' => $fieldLabel,
							'type' => $fieldDefinition->getType(),
						],
						'change' => [
							'previousValue' => $subject->getPreviousValue(),
							'currentValue' => $subject->getCurrentValue(),
							'previousVisibility' => $subject->getPreviousVisibility(),
							'currentVisibility' => $subject->getCurrentVisibility(),
						],
					], JSON_THROW_ON_ERROR),
					'timeout' => IClient::DEFAULT_REQUEST_TIMEOUT,
				]);
			}
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}

	private function isValidWebhookUrl(string $operation): bool {
		$webhookUrl = trim($operation);
		if ($webhookUrl === '' || filter_var($webhookUrl, FILTER_VALIDATE_URL) === false) {
			return false;
		}

		$scheme = strtolower((string)parse_url($webhookUrl, PHP_URL_SCHEME));
		return $scheme === 'http' || $scheme === 'https';
	}
}
