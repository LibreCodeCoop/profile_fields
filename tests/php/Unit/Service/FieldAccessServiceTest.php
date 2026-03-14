<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Service;

use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Service\FieldAccessService;
use PHPUnit\Framework\TestCase;

class FieldAccessServiceTest extends TestCase {
	private FieldAccessService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->service = new FieldAccessService();
	}

	public function testAdminCanAlwaysViewEditAndChangeVisibility(): void {
		$definition = $this->buildDefinition(false, false);

		$this->assertTrue($this->service->canViewValue(null, 'alice', FieldVisibility::PRIVATE->value, true));
		$this->assertTrue($this->service->canEditValue(null, 'alice', $definition, true));
		$this->assertTrue($this->service->canChangeVisibility(null, 'alice', true));
	}

	public function testOwnerCanAlwaysViewOwnValue(): void {
		$this->assertTrue($this->service->canViewValue('alice', 'alice', FieldVisibility::PRIVATE->value, false));
	}

	public function testAuthenticatedUserCanViewUsersVisibility(): void {
		$this->assertTrue($this->service->canViewValue('bob', 'alice', FieldVisibility::USERS->value, false));
	}

	public function testAnonymousUserCannotViewUsersVisibility(): void {
		$this->assertFalse($this->service->canViewValue(null, 'alice', FieldVisibility::USERS->value, false));
	}

	public function testPrivateValueIsHiddenFromOtherUsers(): void {
		$this->assertFalse($this->service->canViewValue('bob', 'alice', FieldVisibility::PRIVATE->value, false));
	}

	public function testOwnerCanEditOnlyWhenUserEditableAndNotAdminOnly(): void {
		$this->assertTrue($this->service->canEditValue('alice', 'alice', $this->buildDefinition(false, true), false));
		$this->assertFalse($this->service->canEditValue('alice', 'alice', $this->buildDefinition(true, true), false));
		$this->assertFalse($this->service->canEditValue('alice', 'alice', $this->buildDefinition(false, false), false));
	}

	public function testOnlyOwnerOrAdminCanChangeVisibility(): void {
		$this->assertTrue($this->service->canChangeVisibility('alice', 'alice', false));
		$this->assertFalse($this->service->canChangeVisibility('bob', 'alice', false));
		$this->assertFalse($this->service->canChangeVisibility(null, 'alice', false));
	}

	private function buildDefinition(bool $adminOnly, bool $userEditable): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setType(FieldType::TEXT->value);
		$definition->setInitialVisibility(FieldVisibility::PRIVATE->value);
		$definition->setAdminOnly($adminOnly);
		$definition->setUserEditable($userEditable);
		$definition->setUserVisible(true);
		return $definition;
	}
}
