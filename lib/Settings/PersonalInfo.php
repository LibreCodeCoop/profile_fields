<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class PersonalInfo implements ISettings {
	#[\Override]
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings-personal-info');
	}

	#[\Override]
	public function getSection(): string {
		return 'personal-info';
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}
}
