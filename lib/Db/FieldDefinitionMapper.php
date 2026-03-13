<?php

// SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
//
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace OCA\ProfileFields\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<FieldDefinition> */
class FieldDefinitionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'profile_fields_definitions', FieldDefinition::class);
	}

	public function findByFieldKey(string $fieldKey): ?FieldDefinition {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'field_key',
			'label',
			'type',
			'admin_only',
			'user_editable',
			'user_visible',
			'initial_visibility',
			'sort_order',
			'active',
			'created_at',
			'updated_at',
		)
			->from('profile_fields_definitions')
			->where($qb->expr()->eq('field_key', $qb->createNamedParameter($fieldKey)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	public function findById(int $id): ?FieldDefinition {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'field_key',
			'label',
			'type',
			'admin_only',
			'user_editable',
			'user_visible',
			'initial_visibility',
			'sort_order',
			'active',
			'created_at',
			'updated_at',
		)
			->from('profile_fields_definitions')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}
	}

	/**
	 * @return list<FieldDefinition>
	 */
	public function findAllOrdered(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'field_key',
			'label',
			'type',
			'admin_only',
			'user_editable',
			'user_visible',
			'initial_visibility',
			'sort_order',
			'active',
			'created_at',
			'updated_at',
		)
			->from('profile_fields_definitions')
			->orderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}

	/**
	 * @return list<FieldDefinition>
	 */
	public function findActiveOrdered(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			'id',
			'field_key',
			'label',
			'type',
			'admin_only',
			'user_editable',
			'user_visible',
			'initial_visibility',
			'sort_order',
			'active',
			'created_at',
			'updated_at',
		)
			->from('profile_fields_definitions')
			->where($qb->expr()->eq('active', $qb->createNamedParameter(true)))
			->orderBy('sort_order', 'ASC')
			->addOrderBy('id', 'ASC');

		return $this->findEntities($qb);
	}
}
