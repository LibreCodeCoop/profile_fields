<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Controller;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Controller\FieldValueApiController;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldAccessService;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\AppFramework\App;
use OCP\AppFramework\Http;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

/**
 * @group DB
 */
class FieldValueApiControllerTest extends TestCase {
	protected ?string $currentUserId = null;
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private FieldDefinitionService $fieldDefinitionService;
	private FieldValueService $fieldValueService;
	private FieldAccessService $fieldAccessService;
	private IUserManager $userManager;
	/** @var array<int, FieldDefinition> */
	private array $createdDefinitions = [];
	/** @var array<int, FieldValue> */
	private array $createdValues = [];
	/** @var array<string, string> */
	private array $createdUserIds = [];

	protected function setUp(): void {
		parent::setUp();

		$container = (new App(Application::APP_ID))->getContainer();
		$this->fieldDefinitionMapper = $container->get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $container->get(FieldValueMapper::class);
		$this->fieldDefinitionService = $container->get(FieldDefinitionService::class);
		$this->fieldValueService = $container->get(FieldValueService::class);
		$this->fieldAccessService = $container->get(FieldAccessService::class);
		$this->userManager = $container->get(IUserManager::class);

		self::ensureSchemaExists($container->get(IDBConnection::class));
	}

	protected function tearDown(): void {
		foreach ($this->createdValues as $value) {
			$this->fieldValueMapper->delete($value);
		}

		foreach ($this->createdDefinitions as $definition) {
			$storedDefinition = $this->fieldDefinitionMapper->findById($definition->getId());
			if ($storedDefinition !== null) {
				$this->fieldDefinitionMapper->delete($storedDefinition);
			}
		}

		foreach ($this->createdUserIds as $userId) {
			$user = $this->userManager->get($userId);
			if ($user instanceof IUser) {
				$user->delete();
			}
		}

		parent::tearDown();
	}

	public function testSelfServiceFlowListsEditableFieldsAndUpdatesVisibility(): void {
		$userId = $this->createUser('pf_self');
		$this->currentUserId = $userId;
		$fieldKey = 'employee_number_self_integration';
		$definition = $this->createDefinition(
			$fieldKey,
			'Employee number',
			FieldType::NUMBER->value,
			false,
			true,
			FieldVisibility::PRIVATE->value,
			0,
			true,
		);
		$this->insertValue($definition->getId(), $userId, '{"value":42}', FieldVisibility::PRIVATE->value);

		$controller = new FieldValueApiController(
			$this->createMock(IRequest::class),
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->fieldAccessService,
			$this->currentUserId,
		);

		$listResponse = $controller->index();
		$this->assertSame(Http::STATUS_OK, $listResponse->getStatus());
		$matchingFields = array_values(array_filter(
			$listResponse->getData(),
			static fn (array $field): bool => $field['definition']['field_key'] === $fieldKey,
		));
		$this->assertCount(1, $matchingFields);
		$this->assertSame($fieldKey, $matchingFields[0]['definition']['field_key']);
		$this->assertSame(['value' => 42], $matchingFields[0]['value']['value']);

		$upsertResponse = $controller->upsert($definition->getId(), 99, FieldVisibility::USERS->value);
		$this->assertSame(Http::STATUS_OK, $upsertResponse->getStatus());
		$this->assertSame(['value' => 99], $upsertResponse->getData()['value']);
		$this->assertSame('users', $upsertResponse->getData()['current_visibility']);

		$visibilityResponse = $controller->updateVisibility($definition->getId(), FieldVisibility::PUBLIC->value);
		$this->assertSame(Http::STATUS_OK, $visibilityResponse->getStatus());
		$this->assertSame('public', $visibilityResponse->getData()['current_visibility']);
	}

	private function createDefinition(
		string $fieldKey,
		string $label,
		string $type,
		bool $adminOnly,
		bool $userEditable,
		string $initialVisibility,
		int $sortOrder,
		bool $active,
	): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setFieldKey($fieldKey);
		$definition->setLabel($label);
		$definition->setType($type);
		$definition->setEditPolicy(($adminOnly || !$userEditable) ? FieldEditPolicy::ADMINS->value : FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(match ($initialVisibility) {
			'public' => FieldExposurePolicy::PUBLIC->value,
			'users' => FieldExposurePolicy::USERS->value,
			default => FieldExposurePolicy::PRIVATE->value,
		});
		$definition->setSortOrder($sortOrder);
		$definition->setActive($active);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());

		$storedDefinition = $this->fieldDefinitionMapper->insert($definition);
		$this->createdDefinitions[$storedDefinition->getId()] = $storedDefinition;

		return $storedDefinition;
	}

	private function createUser(string $userId): string {
		if ($this->userManager->get($userId) === null) {
			$this->userManager->createUser($userId, $userId);
			$this->createdUserIds[$userId] = $userId;
		}

		return $userId;
	}

	private function insertValue(int $fieldDefinitionId, string $userId, string $valueJson, string $visibility): FieldValue {
		$value = new FieldValue();
		$value->setFieldDefinitionId($fieldDefinitionId);
		$value->setUserUid($userId);
		$value->setValueJson($valueJson);
		$value->setCurrentVisibility($visibility);
		$value->setUpdatedByUid($userId);
		$value->setUpdatedAt(new \DateTime());

		$storedValue = $this->fieldValueMapper->insert($value);
		$this->createdValues[$storedValue->getId()] = $storedValue;

		return $storedValue;
	}

	private static function ensureSchemaExists(IDBConnection $connection): void {
		if ($connection->tableExists('profile_fields_definitions') && $connection->tableExists('profile_fields_values')) {
			return;
		}

		$nullOutputClass = '\\OC\\Migration\\NullOutput';
		$schemaWrapperClass = '\\OC\\DB\\SchemaWrapper';

		if (!class_exists($nullOutputClass) || !class_exists($schemaWrapperClass) || !method_exists($connection, 'getInner')) {
			throw new \RuntimeException('Expected ConnectionAdapter in integration test setup');
		}

		$migration = new Version1000Date20260309120000();
		/** @var IOutput $output */
		$output = new $nullOutputClass();
		$schema = $migration->changeSchema(
			$output,
			static function () use ($connection, $schemaWrapperClass): ISchemaWrapper {
				/** @var ISchemaWrapper $schemaWrapper */
				$schemaWrapper = new $schemaWrapperClass(call_user_func([$connection, 'getInner']));

				return $schemaWrapper;
			},
			[],
		);

		if ($schema instanceof ISchemaWrapper && method_exists($schema, 'getWrappedSchema')) {
			/** @var \Doctrine\DBAL\Schema\Schema $wrappedSchema */
			$wrappedSchema = call_user_func([$schema, 'getWrappedSchema']);
			$connection->migrateToSchema($wrappedSchema);
		}
	}
}
