<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Service;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\BackgroundJob\UserDeletedCleanupJob;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldType;
use OCA\ProfileFields\Enum\FieldVisibility;
use OCA\ProfileFields\Listener\UserDeletedCleanupListener;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldValueService;
use OCA\ProfileFields\Service\OrphanedFieldValueCleanupService;
use OCP\AppFramework\App;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use OCP\User\Events\UserDeletedEvent;
use PHPUnit\Framework\TestCase;

/**
 * @group DB
 */
class OrphanedFieldValueCleanupServiceTest extends TestCase {
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private FieldValueService $fieldValueService;
	private OrphanedFieldValueCleanupService $cleanupService;
	private IJobList $jobList;
	private IUserManager $userManager;
	/** @var array<int, FieldDefinition> */
	private array $createdDefinitions = [];
	/** @var array<string, string> */
	private array $createdUserIds = [];

	protected function setUp(): void {
		parent::setUp();

		$container = (new App(Application::APP_ID))->getContainer();
		$this->fieldDefinitionMapper = $container->get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $container->get(FieldValueMapper::class);
		$this->fieldValueService = $container->get(FieldValueService::class);
		$this->cleanupService = $container->get(OrphanedFieldValueCleanupService::class);
		$this->jobList = $container->get(IJobList::class);
		$this->userManager = $container->get(IUserManager::class);

		self::ensureSchemaExists($container->get(IDBConnection::class));
		$this->removeQueuedCleanupJobs();
	}

	protected function tearDown(): void {
		$this->removeQueuedCleanupJobs();

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

	public function testDeleteValuesForDeletedUserRemovesPersistedValues(): void {
		$ownerId = $this->createUser('pf_cleanup_owner_single');
		$definition = $this->createDefinition('cleanup_single', 'Cleanup single');

		$this->fieldValueService->upsert($definition, $ownerId, 'value', $ownerId, FieldVisibility::PRIVATE->value);
		$this->assertCount(1, $this->fieldValueMapper->findByUserUid($ownerId));

		$this->deleteUser($ownerId);

		$deletedValues = $this->cleanupService->deleteValuesForDeletedUser($ownerId);

		$this->assertSame(1, $deletedValues);
		$this->assertCount(0, $this->fieldValueMapper->findByUserUid($ownerId));
	}

	public function testRepairOrphanedValuesDeletesOnlyMissingUsers(): void {
		$this->cleanupService->repairOrphanedValues();

		$existingUserId = $this->createUser('pf_cleanup_existing');
		$deletedUserId = $this->createUser('pf_cleanup_deleted');
		$definition = $this->createDefinition('cleanup_bulk', 'Cleanup bulk');

		$this->fieldValueService->upsert($definition, $existingUserId, 'keep', $existingUserId, FieldVisibility::PRIVATE->value);
		$this->fieldValueService->upsert($definition, $deletedUserId, 'remove', $deletedUserId, FieldVisibility::PRIVATE->value);

		$this->deleteUser($deletedUserId);

		$result = $this->cleanupService->repairOrphanedValues();

		$this->assertGreaterThanOrEqual(2, $result['checked_user_uids']);
		$this->assertSame(1, $result['orphaned_user_uids']);
		$this->assertSame(1, $result['deleted_values']);
		$this->assertCount(1, $this->fieldValueMapper->findByUserUid($existingUserId));
		$this->assertCount(0, $this->fieldValueMapper->findByUserUid($deletedUserId));
	}

	public function testListenerQueuesJobAndJobRemovesDeletedUserValues(): void {
		$ownerId = $this->createUser('pf_cleanup_queued_owner');
		$definition = $this->createDefinition('cleanup_queued', 'Cleanup queued');

		$this->fieldValueService->upsert($definition, $ownerId, 'value', $ownerId, FieldVisibility::PRIVATE->value);
		$this->assertCount(1, $this->fieldValueMapper->findByUserUid($ownerId));

		$user = $this->userManager->get($ownerId);
		$this->assertInstanceOf(IUser::class, $user);
		$user->delete();

		$listener = new UserDeletedCleanupListener($this->jobList);
		$listener->handle(new UserDeletedEvent($user));

		$job = $this->findQueuedCleanupJobForUser($ownerId);
		$this->assertInstanceOf(UserDeletedCleanupJob::class, $job);

		$job->start($this->jobList);

		$this->assertCount(0, $this->fieldValueMapper->findByUserUid($ownerId));
		$this->assertNull($this->findQueuedCleanupJobForUser($ownerId));
	}

	private function createDefinition(string $fieldKey, string $label): FieldDefinition {
		$definition = new FieldDefinition();
		$definition->setFieldKey($fieldKey);
		$definition->setLabel($label);
		$definition->setType(FieldType::TEXT->value);
		$definition->setEditPolicy(\OCA\ProfileFields\Enum\FieldEditPolicy::USERS->value);
		$definition->setExposurePolicy(\OCA\ProfileFields\Enum\FieldExposurePolicy::PRIVATE->value);
		$definition->setSortOrder(0);
		$definition->setActive(true);
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

	private function deleteUser(string $userId): void {
		$user = $this->userManager->get($userId);
		if ($user instanceof IUser) {
			$user->delete();
		}

		unset($this->createdUserIds[$userId]);
	}

	private function findQueuedCleanupJobForUser(string $userId): ?IJob {
		foreach ($this->jobList->getJobsIterator(UserDeletedCleanupJob::class, null, 0) as $job) {
			$argument = $job->getArgument();
			if (is_array($argument) && ($argument['userUid'] ?? null) === $userId) {
				return $job;
			}
		}

		return null;
	}

	private function removeQueuedCleanupJobs(): void {
		foreach ($this->jobList->getJobsIterator(UserDeletedCleanupJob::class, null, 0) as $job) {
			$this->jobList->removeById($job->getId());
		}
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

		$innerConnection = call_user_func([$connection, 'getInner']);
		/** @var ISchemaWrapper $schema */
		$schema = new $schemaWrapperClass($innerConnection->createSchema());
		$migration = new Version1000Date20260309120000();
		$schema = $migration->changeSchema(new $nullOutputClass(), static fn () => $schema, ['appVersion' => '0.0.1']);
		if (!$schema instanceof ISchemaWrapper) {
			throw new \RuntimeException('Expected schema wrapper after migration');
		}

		call_user_func([$connection, 'migrateToSchema'], $schema);
	}
}
