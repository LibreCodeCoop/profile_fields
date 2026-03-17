<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Listener;

use OCA\ProfileFields\Workflow\LogProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\NotifyUserProfileFieldChangeOperation;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

/**
 * @template-implements IEventListener<RegisterOperationsEvent>
 */
class RegisterWorkflowOperationListener implements IEventListener {
	public function __construct(
		private LogProfileFieldChangeOperation $operation,
		private NotifyUserProfileFieldChangeOperation $notifyUserOperation,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof RegisterOperationsEvent) {
			return;
		}

		$event->registerOperation($this->operation);
		$event->registerOperation($this->notifyUserOperation);
	}
}
