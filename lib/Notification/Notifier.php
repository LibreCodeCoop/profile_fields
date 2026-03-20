<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Notification;

use OCA\ProfileFields\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

class Notifier implements INotifier {
	public function __construct(
		private IFactory $factory,
		private IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getID(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getName(): string {
		return $this->factory->get(Application::APP_ID)->t('Profile fields');
	}

	#[\Override]
	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException();
		}

		$l10n = $this->factory->get(Application::APP_ID, $languageCode);

		if ($notification->getSubject() === 'profile_field_updated') {
			$notification->setParsedSubject($l10n->t('Profile field updated'));
		} elseif ($notification->getParsedSubject() === '') {
			$notification->setParsedSubject($notification->getSubject());
		}

		if ($notification->getMessage() === 'profile_field_updated_message') {
			// TRANSLATORS %1$s is the actor user ID, %2$s is the affected user ID, %3$s is the profile field label.
			$notification->setParsedMessage($l10n->t(
				'%1$s changed profile field "%3$s" for user %2$s.',
				$notification->getMessageParameters(),
			));
		} elseif ($notification->getMessage() !== '' && $notification->getParsedMessage() === '') {
			$notification->setParsedMessage($notification->getMessage());
		}

		if ($notification->getIcon() === '') {
			$notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app.svg')));
		}

		return $notification;
	}
}
