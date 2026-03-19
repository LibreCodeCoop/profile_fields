<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Search;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class ProfileFieldSearchProvider implements IProvider {
	private const MIN_SEARCH_LENGTH = 2;

	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private ProfileFieldDirectorySearchService $searchService,
	) {
	}

	#[\Override]
	public function getId(): string {
		return 'profile_fields.directory';
	}

	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Profile directory');
	}

	#[\Override]
	public function getOrder(string $route, array $routeParameters): ?int {
		return str_starts_with($route, 'settings.Users.usersList') ? 35 : 65;
	}

	#[\Override]
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$term = trim($query->getTerm());
		if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
			return SearchResult::complete($this->getName(), []);
		}

		$cursor = $this->normalizeCursor($query->getCursor());
		$result = $this->searchService->search($user, $term, $query->getLimit(), $cursor);
		$entries = array_map(fn (array $item): SearchResultEntry => $this->buildEntry($item), $result['items']);
		if ($cursor + count($entries) >= $result['total']) {
			return SearchResult::complete($this->getName(), $entries);
		}

		return SearchResult::paginated(
			$this->getName(),
			$entries,
			$cursor + count($entries),
		);
	}

	private function normalizeCursor(int|string|null $cursor): int {
		if ($cursor === null || $cursor === '') {
			return 0;
		}

		if (is_int($cursor)) {
			return $cursor;
		}

		if (preg_match('/^-?\d+$/', $cursor) === 1) {
			return (int)$cursor;
		}

		return 0;
	}

	/**
	 * @param array{
	 *     user_uid: string,
	 *     display_name: string,
	 *     matched_fields: list<array{
	 *         field_key: string,
	 *         field_label: string,
	 *         value: string
	 *     }>
	 * } $item
	 */
	private function buildEntry(array $item): SearchResultEntry {
		$thumbnailUrl = $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', [
			'userId' => $item['user_uid'],
			'size' => 64,
		]);
		$resourceUrl = $this->urlGenerator->linkToRouteAbsolute('settings.Users.usersList') . '?search=' . rawurlencode($item['user_uid']);

		return new SearchResultEntry(
			$thumbnailUrl,
			$item['display_name'],
			$this->buildSubline($item['matched_fields']),
			$resourceUrl,
			'icon-user',
			true,
		);
	}

	/**
	 * @param list<array{field_key: string, field_label: string, value: string}> $matchedFields
	 */
	private function buildSubline(array $matchedFields): string {
		$parts = array_map(
			static fn (array $match): string => sprintf('%s: %s', $match['field_label'], $match['value']),
			$matchedFields,
		);

		return implode(' • ', $parts);
	}
}
