<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method int getFieldDefinitionId()
 * @method void setFieldDefinitionId(int $value)
 * @method string getUserUid()
 * @method void setUserUid(string $value)
 * @method string getValueJson()
 * @method void setValueJson(string $value)
 * @method string getCurrentVisibility()
 * @method void setCurrentVisibility(string $value)
 * @method string getUpdatedByUid()
 * @method void setUpdatedByUid(string $value)
 * @method \DateTimeInterface getUpdatedAt()
 * @method void setUpdatedAt(\DateTimeInterface $value)
 */
class FieldValue extends Entity {
	protected $fieldDefinitionId;
	protected $userUid;
	protected $valueJson;
	protected $currentVisibility;
	protected $updatedByUid;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('fieldDefinitionId', 'integer');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * @return array{
	 *     id: int,
	 *     field_definition_id: int,
	 *     user_uid: string,
	 *     value_json: string,
	 *     current_visibility: string,
	 *     updated_by_uid: string,
	 *     updated_at: \DateTimeInterface,
	 * }
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'field_definition_id' => $this->getFieldDefinitionId(),
			'user_uid' => $this->getUserUid(),
			'value_json' => $this->getValueJson(),
			'current_visibility' => $this->getCurrentVisibility(),
			'updated_by_uid' => $this->getUpdatedByUid(),
			'updated_at' => $this->getUpdatedAt(),
		];
	}
}
