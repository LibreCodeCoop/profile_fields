<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\IDelegatedSettings;

class Admin implements IDelegatedSettings {
	#[\Override]
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings-admin');
	}

	#[\Override]
	public function getSection(): string {
		return Application::APP_ID;
	}

	#[\Override]
	public function getPriority(): int {
		return 30;
	}

	#[\Override]
	public function getName(): ?string {
		return null;
	}

	#[\Override]
	public function getAuthorizedAppConfig(): array {
		return [
			Application::APP_ID => ['/.*/'],
		];
	}
}
