<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

class ProfileFieldValueSubjectContext {
	private ?ProfileFieldValueWorkflowSubject $workflowSubject = null;

	public function set(ProfileFieldValueWorkflowSubject $workflowSubject): void {
		$this->workflowSubject = $workflowSubject;
	}

	public function get(): ?ProfileFieldValueWorkflowSubject {
		return $this->workflowSubject;
	}

	public function clear(): void {
		$this->workflowSubject = null;
	}
}
