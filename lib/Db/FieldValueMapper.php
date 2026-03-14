<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<FieldValue> */
class FieldValueMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'profile_fields_values', FieldValue::class);
	}

	public function findByFieldDefinitionIdAndUserUid(int $fieldDefinitionId, string $userUid): ?FieldValue {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('profile_fields_values')
			->where($qb->expr()->eq('field_definition_id', $qb->createNamedParameter($fieldDefinitionId)))
			->andWhere($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return list<FieldValue>
	 */
	public function findAllOrdered(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('profile_fields_values')
			->orderBy('field_definition_id', 'ASC')
			->addOrderBy('user_uid', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return list<FieldValue>
	 */
	public function findByUserUid(string $userUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('profile_fields_values')
			->where($qb->expr()->eq('user_uid', $qb->createNamedParameter($userUid)))
			->orderBy('field_definition_id', 'ASC');

		return $this->findEntities($qb);
	}

	public function hasValuesForFieldDefinitionId(int $fieldDefinitionId): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'value_count'))
			->from('profile_fields_values')
			->where($qb->expr()->eq('field_definition_id', $qb->createNamedParameter($fieldDefinitionId)));

		$cursor = $qb->executeQuery();
		$result = $cursor->fetchOne();
		$cursor->closeCursor();

		return (int)$result > 0;
	}

	/**
	 * @return list<FieldValue>
	 */
	public function findByFieldDefinitionIdAndValueJson(int $fieldDefinitionId, string $valueJson): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('profile_fields_values')
			->where($qb->expr()->eq('field_definition_id', $qb->createNamedParameter($fieldDefinitionId)))
			->andWhere($qb->expr()->eq('value_json', $qb->createNamedParameter($valueJson)))
			->orderBy('user_uid', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}
}
