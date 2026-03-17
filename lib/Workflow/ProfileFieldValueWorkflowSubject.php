<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Workflow;

use OCA\ProfileFields\Db\FieldDefinition;

final class ProfileFieldValueWorkflowSubject {
	public function __construct(
		private string $userUid,
		private string $actorUid,
		private FieldDefinition $fieldDefinition,
		private string|int|float|bool|null $currentValue,
		private string|int|float|bool|null $previousValue,
		private string $currentVisibility,
		private ?string $previousVisibility,
	) {
	}

	public function getUserUid(): string {
		return $this->userUid;
	}

	public function getActorUid(): string {
		return $this->actorUid;
	}

	public function getFieldDefinition(): FieldDefinition {
		return $this->fieldDefinition;
	}

	public function getCurrentValue(): string|int|float|bool|null {
		return $this->currentValue;
	}

	public function getPreviousValue(): string|int|float|bool|null {
		return $this->previousValue;
	}

	public function getCurrentVisibility(): string {
		return $this->currentVisibility;
	}

	public function getPreviousVisibility(): ?string {
		return $this->previousVisibility;
	}
}
