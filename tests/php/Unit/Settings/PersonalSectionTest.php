<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Settings;

use OCA\ProfileFields\Settings\PersonalInfo;
use PHPUnit\Framework\TestCase;

class PersonalSectionTest extends TestCase {
	public function testSectionMetadataIsExposed(): void {
		$section = new PersonalInfo();

		$this->assertSame('personal-info', $section->getSection());
		$this->assertSame(80, $section->getPriority());
	}
}
