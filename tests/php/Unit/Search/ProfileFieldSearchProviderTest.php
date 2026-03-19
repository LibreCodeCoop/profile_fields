<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Search;

use OCA\ProfileFields\Search\ProfileFieldDirectorySearchService;
use OCA\ProfileFields\Search\ProfileFieldSearchProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProfileFieldSearchProviderTest extends TestCase {
	public function testSearchReturnsEmptyResultForShortTerms(): void {
		$provider = new ProfileFieldSearchProvider(
			$this->buildL10n(),
			$this->createMock(IURLGenerator::class),
			$this->createMock(ProfileFieldDirectorySearchService::class),
		);
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('a');
		$query->method('getLimit')->willReturn(10);
		$query->method('getCursor')->willReturn(0);

		$result = $provider->search($this->createMock(IUser::class), $query);

		$this->assertSame([], $result->jsonSerialize()['entries']);
	}

	public function testSearchNormalizesInitialNullCursorToZero(): void {
		$l10n = $this->buildL10n();
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$searchService = $this->createMock(ProfileFieldDirectorySearchService::class);
		$user = $this->createMock(IUser::class);
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('latam');
		$query->method('getLimit')->willReturn(1);
		$query->method('getCursor')->willReturn(null);

		$searchService->expects($this->once())
			->method('search')
			->with($user, 'latam', 1, 0)
			->willReturn([
				'total' => 2,
				'items' => [[
					'user_uid' => 'alice',
					'display_name' => 'Alice Doe',
					'matched_fields' => [[
						'field_key' => 'region',
						'field_label' => 'Region',
						'value' => 'LATAM',
					]],
				]],
			]);

		$urlGenerator->expects($this->exactly(2))
			->method('linkToRouteAbsolute')
			->willReturnMap([
				['core.avatar.getAvatar', ['userId' => 'alice', 'size' => 64], 'https://cloud.test/avatar/alice'],
				['settings.Users.usersList', [], 'https://cloud.test/settings/users'],
			]);

		$provider = new ProfileFieldSearchProvider($l10n, $urlGenerator, $searchService);
		$result = $provider->search($user, $query)->jsonSerialize();

		$this->assertTrue($result['isPaginated']);
		$this->assertSame(1, $result['cursor']);
	}

	public function testSearchNormalizesNumericStringCursor(): void {
		$l10n = $this->buildL10n();
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$searchService = $this->createMock(ProfileFieldDirectorySearchService::class);
		$user = $this->createMock(IUser::class);
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('latam');
		$query->method('getLimit')->willReturn(1);
		$query->method('getCursor')->willReturn('1');

		$searchService->expects($this->once())
			->method('search')
			->with($user, 'latam', 1, 1)
			->willReturn([
				'total' => 2,
				'items' => [[
					'user_uid' => 'bruno',
					'display_name' => 'Bruno Doe',
					'matched_fields' => [[
						'field_key' => 'region',
						'field_label' => 'Region',
						'value' => 'LATAM',
					]],
				]],
			]);

		$urlGenerator->expects($this->exactly(2))
			->method('linkToRouteAbsolute')
			->willReturnMap([
				['core.avatar.getAvatar', ['userId' => 'bruno', 'size' => 64], 'https://cloud.test/avatar/bruno'],
				['settings.Users.usersList', [], 'https://cloud.test/settings/users'],
			]);

		$provider = new ProfileFieldSearchProvider($l10n, $urlGenerator, $searchService);
		$result = $provider->search($user, $query)->jsonSerialize();

		$this->assertFalse($result['isPaginated']);
	}

	public function testSearchBuildsAvatarBackedEntries(): void {
		$l10n = $this->buildL10n();
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$searchService = $this->createMock(ProfileFieldDirectorySearchService::class);
		$user = $this->createMock(IUser::class);
		$query = $this->createMock(ISearchQuery::class);
		$query->method('getTerm')->willReturn('latam');
		$query->method('getLimit')->willReturn(10);
		$query->method('getCursor')->willReturn(0);

		$searchService->expects($this->once())
			->method('search')
			->with($user, 'latam', 10, 0)
			->willReturn([
				'total' => 1,
				'items' => [[
					'user_uid' => 'alice',
					'display_name' => 'Alice Doe',
					'matched_fields' => [[
						'field_key' => 'region',
						'field_label' => 'Region',
						'value' => 'LATAM',
					]],
				]],
			]);

		$urlGenerator->expects($this->exactly(2))
			->method('linkToRouteAbsolute')
			->willReturnMap([
				['core.avatar.getAvatar', ['userId' => 'alice', 'size' => 64], 'https://cloud.test/avatar/alice'],
				['settings.Users.usersList', [], 'https://cloud.test/settings/users'],
			]);

		$provider = new ProfileFieldSearchProvider($l10n, $urlGenerator, $searchService);
		$result = $provider->search($user, $query)->jsonSerialize();
		$entry = $result['entries'][0]->jsonSerialize();

		$this->assertSame('Profile directory', $result['name']);
		$this->assertCount(1, $result['entries']);
		$this->assertFalse($result['isPaginated']);
		$this->assertSame('Alice Doe', $entry['title']);
		$this->assertSame('Region: LATAM', $entry['subline']);
		$this->assertSame('https://cloud.test/settings/users?search=alice', $entry['resourceUrl']);
	}

	private function buildL10n(): IL10N&MockObject {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => $text);

		return $l10n;
	}
}
