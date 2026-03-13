<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Settings\PersonalInfo;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {
	public function testGetFormReturnsPersonalTemplate(): void {
		$settings = new PersonalInfo();

		$response = $settings->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(Application::APP_ID, $response->getApp());
		$this->assertSame('settings-personal-info', $response->getTemplateName());
	}

	public function testSettingsMetadataMatchesPersonalSection(): void {
		$settings = new PersonalInfo();

		$this->assertSame('personal-info', $settings->getSection());
		$this->assertSame(80, $settings->getPriority());
	}
}
