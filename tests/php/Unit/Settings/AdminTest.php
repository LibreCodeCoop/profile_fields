<?php

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

class AdminTest extends TestCase {
	public function testGetFormReturnsAdminTemplate(): void {
		$settings = new Admin();

		$response = $settings->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(Application::APP_ID, $response->getApp());
		$this->assertSame('settings-admin', $response->getTemplateName());
	}

	public function testSettingsMetadataMatchesAdminSection(): void {
		$settings = new Admin();

		$this->assertSame(Application::APP_ID, $settings->getSection());
		$this->assertSame(30, $settings->getPriority());
		$this->assertNull($settings->getName());
		$this->assertSame([
			Application::APP_ID => ['/.*/'],
		], $settings->getAuthorizedAppConfig());
	}
}
