<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Notification;

use OCA\ProfileFields\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class ProfileFieldWorkflowNotifier implements INotifier {
	public function __construct(
		private IFactory $l10nFactory,
		private IUserManager $userManager,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getID(): string {
		return Application::APP_ID . '_workflow';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Profile field workflow notifications');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID || $notification->getSubject() !== 'profile-field-change-notification') {
			throw new UnknownNotificationException();
		}

		$l10n = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$parameters = $notification->getSubjectParameters();
		$fieldLabel = trim((string)($parameters['fieldLabel'] ?? ''));
		$actorUid = (string)($parameters['actorUid'] ?? '');
		$actorDisplayName = $actorUid;

		if ($actorUid !== '') {
			$actor = $this->userManager->get($actorUid);
			if ($actor !== null) {
				$actorDisplayName = $actor->getDisplayName();
			}
		}

		if ($fieldLabel === '') {
			$fieldLabel = $l10n->t('profile field');
		}

		$message = $actorUid === '' || $actorUid === $notification->getUser()
			? $l10n->t('Your %s profile field was updated.', [$fieldLabel])
			: $l10n->t('%s changed your %s profile field.', [$actorDisplayName, $fieldLabel]);

		return $notification
			->setParsedSubject($l10n->t('Your profile information was updated'))
			->setParsedMessage($message)
			->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/profile.svg')));
	}
}
