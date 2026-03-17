<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Notification;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Notification\ProfileFieldWorkflowNotifier;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\UnknownNotificationException;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileFieldWorkflowNotifierTest extends TestCase {
	private IFactory&MockObject $l10nFactory;
	private IUserManager&MockObject $userManager;
	private IURLGenerator&MockObject $urlGenerator;
	private ProfileFieldWorkflowNotifier $notifier;

	protected function setUp(): void {
		parent::setUp();

		$this->l10nFactory = $this->createMock(IFactory::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters));

		$this->l10nFactory->method('get')
			->with(Application::APP_ID, $this->anything())
			->willReturn($l10n);

		$this->notifier = new ProfileFieldWorkflowNotifier($this->l10nFactory, $this->userManager, $this->urlGenerator);
	}

	public function testPrepareRejectsNotificationsFromOtherApps(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('other-app');

		$this->expectException(UnknownNotificationException::class);

		$this->notifier->prepare($notification, 'en');
	}

	public function testPrepareFormatsNotificationForAffectedUser(): void {
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn(Application::APP_ID);
		$notification->method('getSubject')->willReturn('profile-field-change-notification');
		$notification->method('getSubjectParameters')->willReturn([
			'fieldLabel' => 'Department',
			'actorUid' => 'admin',
		]);
		$notification->method('getUser')->willReturn('alice');

		$actor = $this->createMock(IUser::class);
		$actor->method('getDisplayName')->willReturn('Admin');
		$this->userManager->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($actor);

		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'actions/profile.svg')
			->willReturn('/core/img/actions/profile.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('/core/img/actions/profile.svg')
			->willReturn('https://localhost/core/img/actions/profile.svg');

		$notification->expects($this->once())
			->method('setParsedSubject')
			->with('Your profile information was updated')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setParsedMessage')
			->with('Admin changed your Department profile field.')
			->willReturnSelf();
		$notification->expects($this->once())
			->method('setIcon')
			->with('https://localhost/core/img/actions/profile.svg')
			->willReturnSelf();

		self::assertSame($notification, $this->notifier->prepare($notification, 'en'));
	}
}
