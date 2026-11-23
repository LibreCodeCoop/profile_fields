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

	public function testAdminCanInspectAllPopulatedFieldsForExactUidWithPagination(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$this->groupManager->expects($this->once())->method('isAdmin')->with('admin')->willReturn(true);
		$user = $this->buildUser('Alice Doe');
		$user->method('getUID')->willReturn('alice');
		$this->userManager->expects($this->once())->method('get')->with('alice')->willReturn($user);

		$mailbox = $this->buildDefinition(1, 'mailcow_mailbox', 'Mailbox', userVisible: false);
		$status = $this->buildDefinition(2, 'mail_provisioning_status', 'Authorization status', userVisible: false);
		$this->fieldDefinitionService->expects($this->once())->method('findActiveOrdered')->willReturn([$mailbox, $status]);
		$this->fieldValueMapper->expects($this->once())->method('findByUserUid')->with('alice')->willReturn([
			$this->buildValue(1, 'alice', 'private', 'alice@example.org'),
			$this->buildValue(2, 'alice', 'private', 'pending'),
			$this->buildValue(3, 'alice', 'private', 'ignore-me'),
		]);

		$result = $this->service->searchByUserUid($actor, 'alice', 1, 1);
		$this->assertSame(2, $result['total']);
		$this->assertCount(1, $result['items']);
		$this->assertSame('alice', $result['items'][0]['user_uid']);
		$this->assertSame('Alice Doe', $result['items'][0]['display_name']);
		$this->assertSame('mail_provisioning_status', $result['items'][0]['matched_fields'][0]['field_key']);
		$this->assertSame('pending', $result['items'][0]['matched_fields'][0]['value']);
	}

	public function testExactUidLookupUsesCanonicalUidAndConfiguredOrdering(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$this->groupManager->method('isAdmin')->willReturn(true);
		$user = $this->buildUser('Alice');
		$user->method('getUID')->willReturn('alice');
		$this->userManager->expects($this->once())->method('get')->with('Alice')->willReturn($user);
		$this->fieldDefinitionService->method('findActiveOrdered')->willReturn([
			$this->buildDefinition(2, 'first', 'First'),
			$this->buildDefinition(1, 'second', 'Second'),
		]);
		$this->fieldValueMapper->expects($this->once())->method('findByUserUid')->with('alice')->willReturn([
			$this->buildValue(1, 'alice', 'private', 'last'),
			$this->buildValue(2, 'alice', 'private', 'top'),
		]);
		$result = $this->service->searchByUserUid($actor, 'Alice', 1, 0);
		$this->assertSame(2, $result['total']);
		$this->assertSame('alice', $result['items'][0]['user_uid']);
		$this->assertSame('first', $result['items'][0]['matched_fields'][0]['field_key']);
	}

	public function testExactUidSkipsEmptyValuesButRetainsBooleanFalse(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$this->groupManager->method('isAdmin')->willReturn(true);
		$user = $this->buildUser('Alice');
		$user->method('getUID')->willReturn('alice');
		$this->userManager->method('get')->willReturn($user);
		$this->fieldDefinitionService->method('findActiveOrdered')->willReturn([
			$this->buildDefinition(1, 'empty', 'Empty'),
			$this->buildDefinition(2, 'enabled', 'Enabled'),
		]);
		$false = $this->buildValue(2, 'alice', 'private', '');
		$false->setValueJson('{"value":false}');
		$this->fieldValueMapper->method('findByUserUid')->willReturn([
			$this->buildValue(1, 'alice', 'private', '   '),
			$false,
		]);
		$result = $this->service->searchByUserUid($actor, 'alice', 10, 0);
		$this->assertSame(1, $result['total']);
		$this->assertSame('false', $result['items'][0]['matched_fields'][0]['value']);
	}

	public function testRegularSearchMatchesBooleanWords(): void {
		$this->fieldDefinitionService->method('findActiveOrdered')->willReturn([
			$this->buildDefinition(1, 'enabled', 'Enabled'),
		]);
		$true = $this->buildValue(1, 'alice', 'public', '');
		$true->setValueJson('{"value":true}');
		$false = $this->buildValue(1, 'bob', 'public', '');
		$false->setValueJson('{"value":false}');
		$this->fieldValueMapper->method('findAllOrdered')->willReturn([$true, $false]);
		$this->userManager->method('get')->willReturnCallback(fn (string $uid): IUser => $this->buildUser($uid));
		$trueResult = $this->service->search(null, 'true', 10, 0);
		$this->assertSame(1, $trueResult['total']);
		$this->assertSame('true', $trueResult['items'][0]['matched_fields'][0]['value']);
		$falseResult = $this->service->search(null, 'fal', 10, 0);
		$this->assertSame(1, $falseResult['total']);
		$this->assertSame('false', $falseResult['items'][0]['matched_fields'][0]['value']);
	}

	public function testNonAdminCannotLookupPrivateFieldsByUid(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('groupadmin');
		$this->groupManager->expects($this->once())->method('isAdmin')->with('groupadmin')->willReturn(false);
		$this->fieldValueMapper->expects($this->never())->method('findByUserUid');
		$this->fieldDefinitionService->expects($this->never())->method('findActiveOrdered');
		$this->userManager->expects($this->never())->method('get');

		$this->assertSame(['total' => 0, 'items' => []], $this->service->searchByUserUid($actor, 'alice', 20, 0));
	}

	public function testUnknownUidDoesNotLoadStoredValues(): void {
		$actor = $this->createMock(IUser::class);
		$actor->method('getUID')->willReturn('admin');
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->userManager->method('get')->with('does-not-exist')->willReturn(null);
		$this->fieldValueMapper->expects($this->never())->method('findByUserUid');
		$this->assertSame(['total' => 0, 'items' => []], $this->service->searchByUserUid($actor, 'does-not-exist', 20, 0));
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
