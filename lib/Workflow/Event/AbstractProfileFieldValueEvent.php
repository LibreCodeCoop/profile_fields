<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow\Event;

use OCA\ProfileFields\Workflow\ProfileFieldValueWorkflowSubject;
use OCP\EventDispatcher\Event;

abstract class AbstractProfileFieldValueEvent extends Event {
	public function __construct(
		private ProfileFieldValueWorkflowSubject $workflowSubject,
	) {
		parent::__construct();
	}

	public function getWorkflowSubject(): ProfileFieldValueWorkflowSubject {
		return $this->workflowSubject;
	}
}
