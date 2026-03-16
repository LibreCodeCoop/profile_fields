<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Listener;

use OCA\ProfileFields\AppInfo\Application;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * @template-implements IEventListener<Event>
 */
class BeforeTemplateRenderedListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if ($event::class !== '\\OCA\\Settings\\Events\\BeforeTemplateRenderedEvent') {
			return;
		}

		Application::loadUserManagementAssets();
	}
}
