<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Controller;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Controller\FieldValueAdminApiController;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
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
class FieldValueAdminApiControllerTest extends TestCase {
	protected ?string $currentUserId = null;
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private FieldDefinitionService $fieldDefinitionService;
	private FieldValueService $fieldValueService;
	private IUserManager $userManager;
	/** @var array<int, FieldDefinition> */
	private array $createdDefinitions = [];
	/** @var array<int, \OCA\ProfileFields\Db\FieldValue> */
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

	public function testAdminValueFlow(): void {
		$this->currentUserId = $this->createUser('pf_admin');
		$ownerId = $this->createUser('pf_owner');
		$definition = $this->createDefinition(
			'performance_score_admin_integration',
			'Performance score',
			FieldType::NUMBER->value,
			false,
			true,
			FieldVisibility::PRIVATE->value,
			0,
			true,
		);

		$controller = new FieldValueAdminApiController(
			$this->createMock(IRequest::class),
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->currentUserId,
		);

		$upsertResponse = $controller->upsert($ownerId, $definition->getId(), '9.5', FieldVisibility::PUBLIC->value);
		$this->assertSame(Http::STATUS_OK, $upsertResponse->getStatus());
		$this->assertSame(['value' => 9.5], $upsertResponse->getData()['value']);
		$this->assertSame('public', $upsertResponse->getData()['current_visibility']);

		$valuesResponse = $controller->index($ownerId);
		$this->assertSame(Http::STATUS_OK, $valuesResponse->getStatus());
		$matchingValues = array_values(array_filter(
			$valuesResponse->getData(),
			static fn (array $value): bool => $value['field_definition_id'] === $definition->getId(),
		));
		$this->assertCount(1, $matchingValues);
		$this->assertSame($ownerId, $matchingValues[0]['user_uid']);
	}

	public function testAdminCanLookupUserByCpfAndRetrieveCooperativeFields(): void {
		$this->currentUserId = $this->createUser('pf_admin_lookup');
		$ownerId = $this->createUser('pf_owner_lookup');
		$cpfDefinition = $this->fieldDefinitionService->create([
			'field_key' => 'cpf_lookup_integration',
			'label' => 'CPF',
			'type' => FieldType::TEXT->value,
			'admin_only' => false,
			'user_editable' => false,
			'user_visible' => true,
			'initial_visibility' => FieldVisibility::PRIVATE->value,
			'sort_order' => 0,
			'active' => true,
		]);
		$this->rememberDefinition($cpfDefinition->getId());
		$healthPlanDefinition = $this->fieldDefinitionService->create([
			'field_key' => 'health_plan_type_lookup_integration',
			'label' => 'Health plan type',
			'type' => FieldType::TEXT->value,
			'admin_only' => false,
			'user_editable' => false,
			'user_visible' => true,
			'initial_visibility' => FieldVisibility::PRIVATE->value,
			'sort_order' => 1,
			'active' => true,
		]);
		$this->rememberDefinition($healthPlanDefinition->getId());

		$this->fieldValueService->upsert($cpfDefinition, $ownerId, '12345678900', $this->currentUserId, FieldVisibility::PRIVATE->value);
		$this->fieldValueService->upsert($healthPlanDefinition, $ownerId, 'coop-premium', $this->currentUserId, FieldVisibility::PRIVATE->value);

		$controller = new FieldValueAdminApiController(
			$this->createMock(IRequest::class),
			$this->fieldDefinitionService,
			$this->fieldValueService,
			$this->currentUserId,
		);

		$response = $controller->lookup('cpf_lookup_integration', '12345678900');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($ownerId, $response->getData()['user_uid']);
		$this->assertSame(['value' => '12345678900'], $response->getData()['fields']['cpf_lookup_integration']['value']['value']);
		$this->assertSame(['value' => 'coop-premium'], $response->getData()['fields']['health_plan_type_lookup_integration']['value']['value']);
	}

	private function rememberDefinition(int $definitionId): void {
		$definition = $this->fieldDefinitionMapper->findById($definitionId);
		if ($definition !== null) {
			$this->createdDefinitions[$definition->getId()] = $definition;
		}
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
		$definition->setAdminOnly($adminOnly);
		$definition->setUserEditable($userEditable);
		$definition->setUserVisible(true);
		$definition->setInitialVisibility($initialVisibility);
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
