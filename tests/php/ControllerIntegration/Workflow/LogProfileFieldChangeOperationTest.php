<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Workflow;

use OCA\ProfileFields\AppInfo\Application as ProfileFieldsApplication;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Workflow\LogProfileFieldChangeOperation;
use OCA\ProfileFields\Workflow\ProfileFieldValueEntity;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\UserProfileFieldCheck;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\ISchemaWrapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Migration\IOutput;
use OCP\WorkflowEngine\IManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class WorkflowTestEventDispatcher implements IEventDispatcher {
	/** @var array<string, list<callable>> */
	private array $listeners = [];

	public function addListener(string $eventName, callable $listener, int $priority = 0): void {
		$this->listeners[$eventName] ??= [];
		$this->listeners[$eventName][] = $listener;
	}

	public function removeListener(string $eventName, callable $listener): void {
		if (!isset($this->listeners[$eventName])) {
			return;
		}

		$this->listeners[$eventName] = array_values(array_filter(
			$this->listeners[$eventName],
			static fn (callable $registered): bool => $registered !== $listener,
		));
	}

	public function addServiceListener(string $eventName, string $className, int $priority = 0): void {
	}

	public function hasListeners(string $eventName): bool {
		return isset($this->listeners[$eventName]) && $this->listeners[$eventName] !== [];
	}

	public function dispatch(string $eventName, Event $event): void {
		foreach ($this->listeners[$eventName] ?? [] as $listener) {
			$listener($event);
		}
	}

	public function dispatchTyped(Event $event): void {
		$this->dispatch($event::class, $event);
	}
}

/**
 * @group DB
 */
class LogProfileFieldChangeOperationTest extends TestCase {
	private const FIELD_KEY = 'department_workflow_operation_integration';

	private FieldDefinition $definition;
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private FieldDefinitionService $fieldDefinitionService;
	private IDBConnection $connection;
	private IUserManager $userManager;
	private IUserSession&MockObject $userSession;
	private WorkflowTestEventDispatcher $dispatcher;
	private object $workflowManager;
	private LoggerInterface&MockObject $operationLogger;
	/** @var array<string, string> */
	private array $createdUserIds = [];

	protected function setUp(): void {
		parent::setUp();

		$app = new \OCP\AppFramework\App(ProfileFieldsApplication::APP_ID);
		$appContainer = $app->getContainer();

		$this->fieldDefinitionMapper = $appContainer->get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $appContainer->get(FieldValueMapper::class);
		$this->fieldDefinitionService = $appContainer->get(FieldDefinitionService::class);
		$this->connection = $appContainer->get(IDBConnection::class);
		$this->userManager = $appContainer->get(IUserManager::class);

		self::ensureSchemaExists($this->connection);
		$this->clearWorkflowTables();
		$this->deleteDefinitionIfExists(self::FIELD_KEY);
		$this->definition = $this->createDefinition(self::FIELD_KEY);

		$this->dispatcher = new WorkflowTestEventDispatcher();
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn(null);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters));

		$generalLogger = $this->createMock(LoggerInterface::class);
		$workflowLoggerClass = 'OCA\\WorkflowEngine\\Service\\Logger';
		$flowLogger = $this->createMock($workflowLoggerClass);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueBool')->willReturn(false);

		$cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturn('/core/img/actions/profile.svg');

		$subjectContext = new ProfileFieldValueSubjectContext();
		$fieldValueService = new FieldValueService($this->fieldValueMapper, $this->dispatcher);
		$check = new UserProfileFieldCheck(
			$this->userSession,
			$l10n,
			$this->fieldDefinitionService,
			$fieldValueService,
			$subjectContext,
		);

		$this->operationLogger = $this->createMock(LoggerInterface::class);
		$entity = new ProfileFieldValueEntity($l10n, $urlGenerator, $subjectContext);
		$operation = new LogProfileFieldChangeOperation($this->operationLogger, $l10n, $urlGenerator, $subjectContext);

		$container = $this->createMock(ContainerInterface::class);
		$workflowManagerClass = 'OCA\\WorkflowEngine\\Manager';
		$this->workflowManager = new $workflowManagerClass(
			$this->connection,
			$container,
			$l10n,
			$generalLogger,
			$this->userSession,
			$this->dispatcher,
			$appConfig,
			$cacheFactory,
		);
		$container->method('get')
			->willReturnCallback(function (string $id) use ($check, $entity, $operation, $flowLogger, $workflowLoggerClass, $workflowManagerClass): mixed {
				return match (ltrim($id, '\\')) {
					$workflowManagerClass => $this->workflowManager,
					UserProfileFieldCheck::class => $check,
					ProfileFieldValueEntity::class => $entity,
					LogProfileFieldChangeOperation::class => $operation,
					$workflowLoggerClass => $flowLogger,
					default => throw new \RuntimeException(sprintf('Unknown service %s', $id)),
				};
			});

		$this->workflowManager->registerCheck($check);
		$this->workflowManager->registerEntity($entity);
		$this->workflowManager->registerOperation($operation);

		$scopeContextClass = 'OCA\\WorkflowEngine\\Helper\\ScopeContext';
		$this->workflowManager->addOperation(
			LogProfileFieldChangeOperation::class,
			'profile-fields-operation-trigger',
			[[
				'class' => UserProfileFieldCheck::class,
				'operator' => 'is',
				'value' => json_encode([
					'field_key' => self::FIELD_KEY,
					'value' => 'engineering',
				], JSON_THROW_ON_ERROR),
			]],
			'',
			new $scopeContextClass(IManager::SCOPE_ADMIN),
			ProfileFieldValueEntity::class,
			[\OCA\ProfileFields\Workflow\Event\ProfileFieldValueUpdatedEvent::class],
		);

		$workflowAppClass = 'OCA\\WorkflowEngine\\AppInfo\\Application';
		$workflowApp = new $workflowAppClass();
		$bootContext = $this->createMock(IBootContext::class);
		$bootContext->expects($this->once())
			->method('injectFn')
			->willReturnCallback(function (callable $fn) use ($container, $generalLogger): mixed {
				return $fn($this->dispatcher, $container, $generalLogger);
			});
		$workflowApp->boot($bootContext);
	}

	protected function tearDown(): void {
		$this->clearWorkflowTables();
		$this->deleteDefinitionIfExists(self::FIELD_KEY);

		foreach ($this->createdUserIds as $userId) {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$user->delete();
			}
		}

		parent::tearDown();
	}

	public function testUpsertDispatchTriggersConfiguredWorkflowOperation(): void {
		$userId = $this->createUser('pf_workflow_op_subject');
		$this->insertValueForUser($this->definition, $userId, 'finance');

		$this->operationLogger->expects($this->once())
			->method('notice')
			->with(
				'Profile field workflow rule matched',
				$this->callback(static function (array $context) use ($userId): bool {
					return ($context['rule_name'] ?? null) === 'profile-fields-operation-trigger'
						&& ($context['field_key'] ?? null) === self::FIELD_KEY
						&& ($context['user_uid'] ?? null) === $userId
						&& ($context['previous_value'] ?? null) === 'finance'
						&& ($context['current_value'] ?? null) === 'engineering';
				}),
			);

		$fieldValueService = new FieldValueService($this->fieldValueMapper, $this->dispatcher);
		$fieldValueService->upsert($this->definition, $userId, 'engineering', 'admin');
	}

	private function createDefinition(string $fieldKey): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setFieldKey($fieldKey);
		$definition->setLabel('Department');
		$definition->setType(FieldType::TEXT->value);
		$definition->setAdminOnly(false);
		$definition->setUserEditable(true);
		$definition->setUserVisible(true);
		$definition->setInitialVisibility(FieldVisibility::USERS->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime());
		$definition->setUpdatedAt(new \DateTime());

		return $this->fieldDefinitionMapper->insert($definition);
	}

	private function createUser(string $userId): string {
		if ($this->userManager->get($userId) === null) {
			$this->userManager->createUser($userId, $userId);
			$this->createdUserIds[$userId] = $userId;
		}

		return $userId;
	}

	private function insertValueForUser(FieldDefinition $definition, string $userId, string $value): void {
		$fieldValue = new FieldValue();
		$fieldValue->setFieldDefinitionId($definition->getId());
		$fieldValue->setUserUid($userId);
		$fieldValue->setValueJson(json_encode(['value' => $value], JSON_THROW_ON_ERROR));
		$fieldValue->setCurrentVisibility(FieldVisibility::USERS->value);
		$fieldValue->setUpdatedByUid($userId);
		$fieldValue->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->insert($fieldValue);
	}

	private function clearWorkflowTables(): void {
		foreach (['flow_operations_scope', 'flow_operations', 'flow_checks'] as $table) {
			$this->connection->getQueryBuilder()
				->delete($table)
				->executeStatement();
		}
	}

	private function deleteDefinitionIfExists(string $fieldKey): void {
		$definition = $this->fieldDefinitionMapper->findByFieldKey($fieldKey);
		if ($definition === null) {
			return;
		}

		foreach ($this->fieldValueMapper->findByFieldDefinitionId($definition->getId()) as $value) {
			$this->fieldValueMapper->delete($value);
		}

		$this->fieldDefinitionMapper->delete($definition);
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
