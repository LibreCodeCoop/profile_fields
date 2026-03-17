<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Workflow;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Workflow\ProfileFieldValueSubjectContext;
use OCA\ProfileFields\Workflow\UserProfileFieldCheck;
use OCP\AppFramework\Services\IAppConfig;
use OCP\DB\ISchemaWrapper;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Migration\IOutput;
use OCP\WorkflowEngine\IComplexOperation;
use OCP\WorkflowEngine\IEntity;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class UserProfileFieldCheckIntegrationTestEntity implements IEntity {
	#[\Override]
	public function getName(): string {
		return 'Profile fields test entity';
	}

	#[\Override]
	public function getIcon(): string {
		return '';
	}

	#[\Override]
	public function getEvents(): array {
		return [];
	}

	#[\Override]
	public function prepareRuleMatcher(IRuleMatcher $ruleMatcher, string $eventName, Event $event): void {
	}

	#[\Override]
	public function isLegitimatedForUserId(string $userId): bool {
		return false;
	}
}

final class UserProfileFieldCheckIntegrationTestOperation implements IComplexOperation {
	#[\Override]
	public function getDisplayName(): string {
		return 'Profile fields integration test operation';
	}

	#[\Override]
	public function getDescription(): string {
		return '';
	}

	#[\Override]
	public function getIcon(): string {
		return '';
	}

	#[\Override]
	public function isAvailableForScope(int $scope): bool {
		return $scope === IManager::SCOPE_ADMIN;
	}

	#[\Override]
	public function validateOperation(string $name, array $checks, string $operation): void {
	}

	#[\Override]
	public function onEvent(string $eventName, Event $event, IRuleMatcher $ruleMatcher): void {
	}

	#[\Override]
	public function getTriggerHint(): string {
		return 'For integration tests';
	}
}

/**
 * @group DB
 */
class UserProfileFieldCheckIntegrationTest extends TestCase {
	private const FIELD_KEY_MATCH = 'department_workflow_integration_match';
	private const FIELD_KEY_CREATOR = 'department_workflow_integration_creator';

	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private FieldDefinitionService $fieldDefinitionService;
	private FieldValueService $fieldValueService;
	private IUserManager $userManager;
	private IUserSession&MockObject $userSession;
	private object $workflowManager;
	private ?IUser $currentUser = null;
	private IDBConnection $connection;
	/** @var array<string, string> */
	private array $createdUserIds = [];

	protected function setUp(): void {
		parent::setUp();

		$app = new \OCP\AppFramework\App(Application::APP_ID);
		$appContainer = $app->getContainer();

		$this->fieldDefinitionMapper = $appContainer->get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $appContainer->get(FieldValueMapper::class);
		$this->fieldDefinitionService = $appContainer->get(FieldDefinitionService::class);
		$this->fieldValueService = $appContainer->get(FieldValueService::class);
		$this->userManager = $appContainer->get(IUserManager::class);
		$this->connection = $appContainer->get(IDBConnection::class);

		self::ensureSchemaExists($this->connection);
		$this->clearWorkflowTables();
		$this->deleteDefinitionIfExists(self::FIELD_KEY_MATCH);
		$this->deleteDefinitionIfExists(self::FIELD_KEY_CREATOR);

		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')
			->willReturnCallback(fn (): ?IUser => $this->currentUser);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')
			->willReturnCallback(static fn (string $text, array $parameters = []): string => $parameters === [] ? $text : vsprintf($text, $parameters));

		$generalLogger = $this->createMock(LoggerInterface::class);
		$workflowLoggerClass = 'OCA\\WorkflowEngine\\Service\\Logger';
		$flowLogger = $this->createMock($workflowLoggerClass);
		$eventDispatcher = $this->createMock(IEventDispatcher::class);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueBool')->willReturn(false);

		$cache = $this->createMock(ICache::class);
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn($cache);

		$check = new UserProfileFieldCheck(
			$this->userSession,
			$l10n,
			$this->fieldDefinitionService,
			$this->fieldValueService,
			new ProfileFieldValueSubjectContext(),
		);

		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')
			->willReturnCallback(function (string $id) use ($flowLogger, $check, $workflowLoggerClass): mixed {
				return match (ltrim($id, '\\')) {
					$workflowLoggerClass => $flowLogger,
					UserProfileFieldCheck::class => $check,
					UserProfileFieldCheckIntegrationTestOperation::class => new UserProfileFieldCheckIntegrationTestOperation(),
					UserProfileFieldCheckIntegrationTestEntity::class => new UserProfileFieldCheckIntegrationTestEntity(),
					default => throw new class(sprintf('Unknown service %s', $id)) extends \RuntimeException implements ContainerExceptionInterface {
					},
				};
			});

		$workflowManagerClass = 'OCA\\WorkflowEngine\\Manager';
		$this->workflowManager = new $workflowManagerClass(
			$this->connection,
			$container,
			$l10n,
			$generalLogger,
			$this->userSession,
			$eventDispatcher,
			$appConfig,
			$cacheFactory,
		);
	}

	protected function tearDown(): void {
		$this->currentUser = null;
		$this->clearWorkflowTables();
		$this->deleteDefinitionIfExists(self::FIELD_KEY_MATCH);
		$this->deleteDefinitionIfExists(self::FIELD_KEY_CREATOR);

		foreach ($this->createdUserIds as $userId) {
			$user = $this->userManager->get($userId);
			if ($user instanceof IUser) {
				$user->delete();
			}
		}

		parent::tearDown();
	}

	public function testConfiguredWorkflowRuleMatchesCurrentSessionUser(): void {
		$this->createWorkflowRuleForDepartment(self::FIELD_KEY_MATCH, 'engineering');

		$matchingUserId = $this->createUser('pf_workflow_match');
		$nonMatchingUserId = $this->createUser('pf_workflow_miss');
		$this->insertValueForUser(self::FIELD_KEY_MATCH, $matchingUserId, '{"value":"engineering"}');
		$this->insertValueForUser(self::FIELD_KEY_MATCH, $nonMatchingUserId, '{"value":"finance"}');

		$this->currentUser = $this->userManager->get($matchingUserId);
		$match = $this->newRuleMatcher()->getFlows();
		$this->assertIsArray($match);
		$this->assertSame('profile-fields-current-user-check', $match['name'] ?? null);

		$this->currentUser = $this->userManager->get($nonMatchingUserId);
		$this->assertSame([], $this->newRuleMatcher()->getFlows());
	}

	public function testConfiguredWorkflowRuleFollowsCurrentAuthenticatedUserInsteadOfRuleCreator(): void {
		$adminUserId = $this->createUser('pf_workflow_admin');
		$this->createWorkflowRuleForDepartment(self::FIELD_KEY_CREATOR, 'engineering');
		$this->insertValueForUser(self::FIELD_KEY_CREATOR, $adminUserId, '{"value":"finance"}');

		$this->currentUser = $this->userManager->get($adminUserId);
		$this->assertSame([], $this->newRuleMatcher()->getFlows());

		$subjectUserId = $this->createUser('pf_workflow_subject');
		$this->insertValueForUser(self::FIELD_KEY_CREATOR, $subjectUserId, '{"value":"engineering"}');

		$this->currentUser = $this->userManager->get($subjectUserId);
		$match = $this->newRuleMatcher()->getFlows();
		$this->assertIsArray($match);
		$this->assertSame('profile-fields-current-user-check', $match['name'] ?? null);
	}

	private function createWorkflowRuleForDepartment(string $fieldKey, string $expectedDepartment): void {
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

		$this->fieldDefinitionMapper->insert($definition);

		$scopeContextClass = 'OCA\\WorkflowEngine\\Helper\\ScopeContext';

		$this->workflowManager->addOperation(
			UserProfileFieldCheckIntegrationTestOperation::class,
			'profile-fields-current-user-check',
			[[
				'class' => UserProfileFieldCheck::class,
				'operator' => 'is',
				'value' => json_encode([
					'field_key' => $fieldKey,
					'value' => $expectedDepartment,
				], JSON_THROW_ON_ERROR),
			]],
			'',
			new $scopeContextClass(IManager::SCOPE_ADMIN),
			UserProfileFieldCheckIntegrationTestEntity::class,
			[],
		);
	}

	private function newRuleMatcher(): IRuleMatcher {
		$ruleMatcher = $this->workflowManager->getRuleMatcher();
		$ruleMatcher->setOperation(new UserProfileFieldCheckIntegrationTestOperation());

		return $ruleMatcher;
	}

	private function createUser(string $userId): string {
		if ($this->userManager->get($userId) === null) {
			$this->userManager->createUser($userId, $userId);
			$this->createdUserIds[$userId] = $userId;
		}

		return $userId;
	}

	private function insertValueForUser(string $fieldKey, string $userId, string $valueJson): void {
		$definition = $this->fieldDefinitionMapper->findByFieldKey($fieldKey);
		if ($definition === null) {
			throw new \RuntimeException('Expected workflow test field definition');
		}

		$value = new FieldValue();
		$value->setFieldDefinitionId($definition->getId());
		$value->setUserUid($userId);
		$value->setValueJson($valueJson);
		$value->setCurrentVisibility(FieldVisibility::USERS->value);
		$value->setUpdatedByUid($userId);
		$value->setUpdatedAt(new \DateTime());

		$this->fieldValueMapper->insert($value);
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
