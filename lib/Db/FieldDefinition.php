<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method int getId()
 * @method void setId(int $value)
 * @method string getFieldKey()
 * @method void setFieldKey(string $value)
 * @method string getLabel()
 * @method void setLabel(string $value)
 * @method string getType()
 * @method void setType(string $value)
 * @method bool getAdminOnly()
 * @method void setAdminOnly(bool $value)
 * @method bool getUserEditable()
 * @method void setUserEditable(bool $value)
 * @method bool getUserVisible()
 * @method void setUserVisible(bool $value)
 * @method string getInitialVisibility()
 * @method void setInitialVisibility(string $value)
 * @method int getSortOrder()
 * @method void setSortOrder(int $value)
 * @method bool getActive()
 * @method void setActive(bool $value)
 * @method string|null getOptions()
 * @method void setOptions(?string $value)
 * @method \DateTimeInterface getCreatedAt()
 * @method void setCreatedAt(\DateTimeInterface $value)
 * @method \DateTimeInterface getUpdatedAt()
 * @method void setUpdatedAt(\DateTimeInterface $value)
 */
class FieldDefinition extends Entity {
	protected $fieldKey;
	protected $label;
	protected $type;
	protected $adminOnly;
	protected $userEditable;
	protected $userVisible;
	protected $initialVisibility;
	protected $sortOrder;
	protected $active;
	protected $options;
	protected $createdAt;
	protected $updatedAt;

	public function __construct() {
		$this->addType('id', 'integer');
		$this->addType('adminOnly', 'boolean');
		$this->addType('userEditable', 'boolean');
		$this->addType('userVisible', 'boolean');
		$this->addType('sortOrder', 'integer');
		$this->addType('active', 'boolean');
		$this->addType('createdAt', 'datetime');
		$this->addType('updatedAt', 'datetime');
	}

	/**
	 * @return array{
	 *     id: int,
	 *     field_key: string,
	 *     label: string,
	 *     type: string,
	 *     admin_only: bool,
	 *     user_editable: bool,
	 *     user_visible: bool,
	 *     initial_visibility: string,
	 *     sort_order: int,
	 *     active: bool,
	 *     created_at: string,
	 *     updated_at: string,
	 * }
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'field_key' => $this->getFieldKey(),
			'label' => $this->getLabel(),
			'type' => $this->getType(),
			'admin_only' => $this->getAdminOnly(),
			'user_editable' => $this->getUserEditable(),
			'user_visible' => $this->getUserVisible(),
			'initial_visibility' => $this->getInitialVisibility(),
			'sort_order' => $this->getSortOrder(),
			'active' => $this->getActive(),
			'created_at' => $this->getCreatedAt()->format(DATE_ATOM),
			'updated_at' => $this->getUpdatedAt()->format(DATE_ATOM),
		];
	}
}
