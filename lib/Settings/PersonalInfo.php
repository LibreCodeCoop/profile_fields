<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class PersonalInfo implements ISettings {
	private const RENAMED_CORE_SECTION = 'OCA\\Settings\\Sections\\Personal\\ProfileContact';

	#[\Override]
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings-personal-info');
	}

	#[\Override]
	public function getSection(): string {
		return $this->coreSectionExists(self::RENAMED_CORE_SECTION) ? 'profile-contact' : 'personal-info';
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}

	protected function coreSectionExists(string $className): bool {
		return class_exists($className);
	}
}
