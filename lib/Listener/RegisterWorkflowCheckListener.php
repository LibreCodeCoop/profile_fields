<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Listener;

use OCA\ProfileFields\Workflow\UserProfileFieldCheck;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\WorkflowEngine\Events\RegisterChecksEvent;

/**
 * @template-implements IEventListener<RegisterChecksEvent>
 */
class RegisterWorkflowCheckListener implements IEventListener {
	public function __construct(
		private UserProfileFieldCheck $check,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof RegisterChecksEvent) {
			return;
		}

		$event->registerCheck($this->check);
	}
}
