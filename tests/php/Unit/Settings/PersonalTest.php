<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Settings;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Settings\PersonalInfo;
use OCP\AppFramework\Http\TemplateResponse;
use PHPUnit\Framework\TestCase;

class PersonalTest extends TestCase {
	private function buildSettings(bool $coreSectionRenamed): PersonalInfo {
		return new class($coreSectionRenamed) extends PersonalInfo {
			public function __construct(
				private bool $coreSectionRenamed,
			) {
			}

			#[\Override]
			protected function coreSectionExists(string $className): bool {
				return $this->coreSectionRenamed;
			}
		};
	}

	public function testGetFormReturnsPersonalTemplate(): void {
		$settings = $this->buildSettings(true);

		$response = $settings->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
		$this->assertSame(Application::APP_ID, $response->getApp());
		$this->assertSame('settings-personal-info', $response->getTemplateName());
	}

	public function testSettingsMetadataMatchesPersonalSection(): void {
		$this->assertSame('personal-info', $this->buildSettings(false)->getSection());
		$this->assertSame(80, $this->buildSettings(false)->getPriority());
	}

	public function testSectionFollowsTheCoreSectionRename(): void {
		$this->assertSame('personal-info', $this->buildSettings(false)->getSection());
		$this->assertSame('profile-contact', $this->buildSettings(true)->getSection());
	}
}
