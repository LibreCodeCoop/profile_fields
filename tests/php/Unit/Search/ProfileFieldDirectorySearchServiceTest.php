<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Search;

use DateTime;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Search\ProfileFieldDirectorySearchService;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileFieldDirectorySearchServiceTest extends TestCase {
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueMapper&MockObject $fieldValueMapper;
	private IUserManager&MockObject $userManager;
	private IGroupManager&MockObject $groupManager;
	private ProfileFieldDirectorySearchService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->service = new ProfileFieldDirectorySearchService(
			$this->fieldDefinitionService,
			$this->fieldValueMapper,
			$this->userManager,
			$this->groupManager,
		);
	}

	public function testSearchReturnsVisibleMatchesGroupedByUser(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('analyst');

		$region = $this->buildDefinition(10, 'region', 'Region');
		$team = $this->buildDefinition(20, 'team', 'Team');

		$this->fieldDefinitionService->expects($this->once())
			->method('findActiveOrdered')
			->willReturn([$region, $team]);
		$this->fieldValueMapper->expects($this->once())
			->method('findAllOrdered')
			->willReturn([
				$this->buildValue(10, 'alice', 'public', 'LATAM'),
				$this->buildValue(20, 'alice', 'users', 'Platform'),
				$this->buildValue(10, 'bob', 'private', 'LATAM'),
			]);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('analyst')
			->willReturn(false);
		$this->userManager->expects($this->once())
			->method('get')
			->with('alice')
			->willReturn($this->buildUser('Alice Doe'));

		$result = $this->service->search($actor, 'latam', 10, 0);

		$this->assertSame(1, $result['total']);
		$this->assertSame('alice', $result['items'][0]['user_uid']);
		$this->assertSame('Alice Doe', $result['items'][0]['display_name']);
		$this->assertSame([
			[
				'field_key' => 'region',
				'field_label' => 'Region',
				'value' => 'LATAM',
			],
		], $result['items'][0]['matched_fields']);
	}

	public function testSearchDoesNotExposePrivateOrHiddenFieldsToNonAdmins(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('alice');

		$publicDefinition = $this->buildDefinition(10, 'region', 'Region');
		$hiddenDefinition = $this->buildDefinition(20, 'secret_region', 'Secret Region', userVisible: false);

		$this->fieldDefinitionService->expects($this->once())
			->method('findActiveOrdered')
			->willReturn([$publicDefinition, $hiddenDefinition]);
		$this->fieldValueMapper->expects($this->once())
			->method('findAllOrdered')
			->willReturn([
				$this->buildValue(10, 'alice', 'private', 'LATAM - Private'),
				$this->buildValue(10, 'bob', 'users', 'LATAM - Users'),
				$this->buildValue(20, 'carol', 'public', 'LATAM - Hidden'),
			]);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('alice')
			->willReturn(false);
		$this->userManager->expects($this->once())
			->method('get')
			->with('bob')
			->willReturn($this->buildUser('Bob Doe'));

		$result = $this->service->search($actor, 'latam', 10, 0);

		$this->assertSame(1, $result['total']);
		$this->assertSame('bob', $result['items'][0]['user_uid']);
		$this->assertSame('LATAM - Users', $result['items'][0]['matched_fields'][0]['value']);
	}

	public function testSearchAllowsAdminToFindPrivateAndHiddenFields(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');

		$hiddenDefinition = $this->buildDefinition(10, 'secret_region', 'Secret Region', userVisible: false);

		$this->fieldDefinitionService->expects($this->once())
			->method('findActiveOrdered')
			->willReturn([$hiddenDefinition]);
		$this->fieldValueMapper->expects($this->once())
			->method('findAllOrdered')
			->willReturn([
				$this->buildValue(10, 'alice', 'private', 'LATAM - Private'),
			]);
		$this->groupManager->expects($this->once())
			->method('isAdmin')
			->with('admin')
			->willReturn(true);
		$this->userManager->expects($this->once())
			->method('get')
			->with('alice')
			->willReturn($this->buildUser('Alice Doe'));

		$result = $this->service->search($actor, 'latam', 10, 0);

		$this->assertSame(1, $result['total']);
		$this->assertSame('alice', $result['items'][0]['user_uid']);
		$this->assertSame('LATAM - Private', $result['items'][0]['matched_fields'][0]['value']);
	}

	public function testSearchSupportsPagination(): void {
		$region = $this->buildDefinition(10, 'region', 'Region');

		$this->fieldDefinitionService->method('findActiveOrdered')->willReturn([$region]);
		$this->fieldValueMapper->method('findAllOrdered')->willReturn([
			$this->buildValue(10, 'alice', 'public', 'LATAM'),
			$this->buildValue(10, 'bruno', 'public', 'LATAM'),
		]);
		$this->userManager->method('get')->willReturnCallback(fn (string $uid): IUser => $this->buildUser(strtoupper($uid)));

		$result = $this->service->search(null, 'latam', 1, 1);

		$this->assertSame(2, $result['total']);
		$this->assertCount(1, $result['items']);
		$this->assertSame('bruno', $result['items'][0]['user_uid']);
	}

	private function buildDefinition(int $id, string $fieldKey, string $label, bool $userVisible = true): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setId($id);
		$definition->setFieldKey($fieldKey);
		$definition->setLabel($label);
		$definition->setType('text');
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy($userVisible ? \OCA\ProfileFields\Enum\FieldExposurePolicy::PRIVATE->value : \OCA\ProfileFields\Enum\FieldExposurePolicy::HIDDEN->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setCreatedAt(new DateTime());
		$definition->setUpdatedAt(new DateTime());

		return $definition;
	}

	private function buildValue(int $fieldDefinitionId, string $userUid, string $visibility, string $value): FieldValue {
		$fieldValue = new FieldValue();
		$fieldValue->setId(random_int(1, 9999));
		$fieldValue->setFieldDefinitionId($fieldDefinitionId);
		$fieldValue->setUserUid($userUid);
		$fieldValue->setValueJson(json_encode(['value' => $value], JSON_THROW_ON_ERROR));
		$fieldValue->setCurrentVisibility($visibility);
		$fieldValue->setUpdatedByUid('admin');
		$fieldValue->setUpdatedAt(new DateTime());

		return $fieldValue;
	}

	private function buildUser(string $displayName): IUser&MockObject {
		$user = $this->createMock(IUser::class);
		$user->method('getDisplayName')->willReturn($displayName);

		return $user;
	}
}
