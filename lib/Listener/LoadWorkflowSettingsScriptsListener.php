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
use OCP\Util;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;

/**
 * @template-implements IEventListener<LoadSettingsScriptsEvent>
 */
class LoadWorkflowSettingsScriptsListener implements IEventListener {
	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof LoadSettingsScriptsEvent) {
			return;
		}

		Util::addScript(Application::APP_ID, 'profile_fields-workflow', 'workflowengine');
	}
}
