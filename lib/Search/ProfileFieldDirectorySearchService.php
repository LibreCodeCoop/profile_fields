<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Search;

use InvalidArgumentException;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;

class ProfileFieldDirectorySearchService {
	private const MAX_MATCHES_PER_USER = 3;

	public function __construct(
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueMapper $fieldValueMapper,
		private IUserManager $userManager,
		private IGroupManager $groupManager,
	) {
	}

	/**
	 * @return array{total: int, items: list<array{
	 *     user_uid: string,
	 *     display_name: string,
	 *     matched_fields: list<array{
	 *         field_key: string,
	 *         field_label: string,
	 *         value: string
	 *     }>
	 * }>}
	 */
	public function search(?IUser $actor, string $term, int $limit, int $offset): array {
		if ($limit < 1) {
			throw new InvalidArgumentException('limit must be greater than 0');
		}

		if ($offset < 0) {
			throw new InvalidArgumentException('offset must be greater than or equal to 0');
		}

		$normalizedTerm = trim(mb_strtolower($term));
		if ($normalizedTerm === '') {
			return ['total' => 0, 'items' => []];
		}

		$actorUid = $actor?->getUID();
		$actorIsAdmin = $actorUid !== null && $this->groupManager->isAdmin($actorUid);
		$definitionsById = [];
		foreach ($this->fieldDefinitionService->findActiveOrdered() as $definition) {
			$definitionsById[$definition->getId()] = $definition;
		}

		if ($definitionsById === []) {
			return ['total' => 0, 'items' => []];
		}

		$matchesByUserUid = [];
		foreach ($this->fieldValueMapper->findAllOrdered() as $value) {
			$definition = $definitionsById[$value->getFieldDefinitionId()] ?? null;
			if ($definition === null) {
				continue;
			}

			if (!$this->isSearchableForActor($definition->getUserVisible(), $value->getCurrentVisibility(), $actorIsAdmin, $actorUid !== null)) {
				continue;
			}

			$scalarValue = $this->extractScalarValue($value);
			if ($scalarValue === null || !str_contains(mb_strtolower($scalarValue), $normalizedTerm)) {
				continue;
			}

			$userUid = $value->getUserUid();
			if (!isset($matchesByUserUid[$userUid])) {
				$user = $this->userManager->get($userUid);
				$matchesByUserUid[$userUid] = [
					'user_uid' => $userUid,
					'display_name' => $this->resolveDisplayName($user, $userUid),
					'matched_fields' => [],
				];
			}

			if (count($matchesByUserUid[$userUid]['matched_fields']) >= self::MAX_MATCHES_PER_USER) {
				continue;
			}

			$matchesByUserUid[$userUid]['matched_fields'][] = [
				'field_key' => $definition->getFieldKey(),
				'field_label' => $definition->getLabel(),
				'value' => $scalarValue,
			];
		}

		$matches = array_values($matchesByUserUid);
		usort($matches, static function (array $left, array $right): int {
			return [$left['display_name'], $left['user_uid']] <=> [$right['display_name'], $right['user_uid']];
		});

		return [
			'total' => count($matches),
			'items' => array_slice($matches, $offset, $limit),
		];
	}

	private function extractScalarValue(FieldValue $value): ?string {
		$decoded = json_decode($value->getValueJson(), true);
		$scalar = $decoded['value'] ?? null;
		if (is_array($scalar) || is_object($scalar) || $scalar === null) {
			return null;
		}

		return trim((string)$scalar);
	}

	private function isSearchableForActor(bool $fieldIsUserVisible, string $currentVisibility, bool $actorIsAdmin, bool $actorIsAuthenticated): bool {
		if ($actorIsAdmin) {
			return true;
		}

		if (!$fieldIsUserVisible) {
			return false;
		}

		return match (FieldVisibility::from($currentVisibility)) {
			FieldVisibility::PUBLIC => true,
			FieldVisibility::USERS => $actorIsAuthenticated,
			FieldVisibility::PRIVATE => false,
		};
	}

	private function resolveDisplayName(?IUser $user, string $fallbackUserUid): string {
		if ($user === null) {
			return $fallbackUserUid;
		}

		$displayName = trim($user->getDisplayName());
		return $displayName !== '' ? $displayName : $fallbackUserUid;
	}
}
