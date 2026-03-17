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
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IOperation;
use OCP\WorkflowEngine\IRuleMatcher;

class NotifyAdminsOrGroupsProfileFieldChangeOperation implements IOperation {
	public function __construct(
		private INotificationManager $notificationManager,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldValueSubjectContext $workflowSubjectContext,
	) {
	}

	#[\Override]
	public function getDisplayName(): string {
		return $this->l10n->t('Notify admins or groups');
	}

	#[\Override]
	public function getDescription(): string {
		return $this->l10n->t('Send an internal notification to configured admins, groups, or users when a profile field change matches the workflow rule.');
	}

	#[\Override]
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('core', 'actions/user-admin.svg');
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
		$config = $this->parseConfig($operation);
		if ($config === null || $this->resolveRecipientUids($config['targets']) === []) {
			throw new \UnexpectedValueException($this->l10n->t('A valid target list is required'));
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
				$config = $this->parseConfig((string)($match['operation'] ?? ''));
				if ($config === null) {
					continue;
				}

				foreach ($this->resolveRecipientUids($config['targets']) as $recipientUid) {
					$subjectText = $this->l10n->t('Profile field updated');
					$messageText = $this->l10n->t(
						'%1$s changed %2$s\'s %3$s profile field.',
						[
							$subject->getActorUid(),
							$subject->getUserUid(),
							$fieldLabel,
						],
					);

					$notification = $this->notificationManager->createNotification();
					$notification
						->setApp(Application::APP_ID)
						->setUser($recipientUid)
						->setObject('profile-field-admin-change', sprintf('%s:%s:%s', (string)($match['id'] ?? 'workflow'), $recipientUid, $fieldDefinition->getFieldKey()))
						->setDateTime(new \DateTime())
						->setSubject('profile_field_updated')
						->setMessage('profile_field_updated_message', [
							$subject->getActorUid(),
							$subject->getUserUid(),
							$fieldLabel,
						])
						->setParsedSubject($subjectText)
						->setParsedMessage($messageText)
						->setIcon($this->urlGenerator->getAbsoluteURL($this->getIcon()));

					$this->notificationManager->notify($notification);
				}
			}
		} finally {
			$this->workflowSubjectContext->clear();
		}
	}

	/**
	 * @return array{targets: string}|null
	 */
	private function parseConfig(string $operation): ?array {
		$config = trim($operation);
		if ($config === '') {
			return ['targets' => 'admin'];
		}

		try {
			$decoded = json_decode($config, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			return null;
		}

		if (!is_array($decoded)) {
			return null;
		}

		$targets = trim((string)($decoded['targets'] ?? ''));
		if ($targets === '') {
			return null;
		}

		return ['targets' => $targets];
	}

	/**
	 * @return list<string>
	 */
	private function resolveRecipientUids(string $targets): array {
		$resolved = [];
		foreach (preg_split('/\s*,\s*/', trim($targets)) ?: [] as $target) {
			if ($target === '' || $target === 'invalid') {
				continue;
			}

			if ($target === 'admin') {
				$adminGroup = $this->groupManager->get('admin');
				foreach ($adminGroup?->getUsers() ?? [] as $admin) {
					$resolved[$admin->getUID()] = $admin->getUID();
				}
				continue;
			}

			if (str_starts_with($target, 'user:')) {
				$uid = substr($target, 5);
				$user = $this->userManager->get($uid);
				if ($user instanceof IUser) {
					$resolved[$uid] = $uid;
				}
				continue;
			}

			if (str_starts_with($target, 'group:')) {
				$group = $this->groupManager->get(substr($target, 6));
				foreach ($group?->getUsers() ?? [] as $user) {
					$resolved[$user->getUID()] = $user->getUID();
				}
			}
		}

		return array_values($resolved);
	}
}
