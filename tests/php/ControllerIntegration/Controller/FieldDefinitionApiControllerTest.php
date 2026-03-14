<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Controller;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Controller\FieldDefinitionApiController;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldDefinitionService;
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
class FieldDefinitionApiControllerTest extends TestCase {
	protected ?string $currentUserId = null;
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldDefinitionService $fieldDefinitionService;
	private IUserManager $userManager;
	/** @var array<int, FieldDefinition> */
	private array $createdDefinitions = [];
	/** @var array<string, string> */
	private array $createdUserIds = [];

	protected function setUp(): void {
		parent::setUp();

		$container = (new App(Application::APP_ID))->getContainer();
		$this->fieldDefinitionMapper = $container->get(FieldDefinitionMapper::class);
		$this->fieldDefinitionService = $container->get(FieldDefinitionService::class);
		$this->userManager = $container->get(IUserManager::class);

		self::ensureSchemaExists($container->get(IDBConnection::class));
	}

	protected function tearDown(): void {
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

	public function testDefinitionCrudFlow(): void {
		$this->currentUserId = $this->createUser('pf_admin');
		$fieldKey = 'performance_score_definition_integration';

		$controller = new FieldDefinitionApiController(
			$this->createMock(IRequest::class),
			$this->fieldDefinitionService,
		);

		$createResponse = $controller->create(
			$fieldKey,
			'Performance score',
			FieldType::NUMBER->value,
			false,
			false,
			true,
			FieldVisibility::USERS->value,
			5,
			true,
		);

		$this->assertSame(Http::STATUS_CREATED, $createResponse->getStatus());
		$this->rememberDefinition($createResponse->getData()['id']);

		$listResponse = $controller->index();
		$this->assertSame(Http::STATUS_OK, $listResponse->getStatus());
		$matchingDefinitions = array_values(array_filter(
			$listResponse->getData(),
			static fn (array $definition): bool => $definition['field_key'] === $fieldKey,
		));
		$this->assertCount(1, $matchingDefinitions);
		$this->assertSame($fieldKey, $matchingDefinitions[0]['field_key']);

		$updateResponse = $controller->update(
			$createResponse->getData()['id'],
			'Performance score updated',
			FieldType::NUMBER->value,
			true,
			false,
			true,
			FieldVisibility::PUBLIC->value,
			6,
			true,
		);

		$this->assertSame(Http::STATUS_OK, $updateResponse->getStatus());
		$this->assertSame('Performance score updated', $updateResponse->getData()['label']);

		$deleteResponse = $controller->delete($createResponse->getData()['id']);
		$this->assertSame(Http::STATUS_OK, $deleteResponse->getStatus());
	}

	private function rememberDefinition(int $definitionId): void {
		$definition = $this->fieldDefinitionMapper->findById($definitionId);
		if ($definition !== null) {
			$this->createdDefinitions[$definition->getId()] = $definition;
		}
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
