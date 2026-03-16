<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\ControllerIntegration\Command\Data;

use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Command\Data\Import;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\DataImportService;
use OCP\AppFramework\App;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @group DB
 */
class ImportTest extends TestCase {
	private FieldDefinitionMapper $fieldDefinitionMapper;
	private FieldValueMapper $fieldValueMapper;
	private DataImportService $dataImportService;
	private IUserManager $userManager;
	/** @var array<string, string> */
	private array $createdUserIds = [];
	/** @var list<string> */
	private array $createdFieldKeys = [];
	/** @var list<string> */
	private array $payloadFiles = [];

	protected function setUp(): void {
		parent::setUp();

		$container = (new App(Application::APP_ID))->getContainer();
		$this->fieldDefinitionMapper = $container->get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = $container->get(FieldValueMapper::class);
		$this->dataImportService = $container->get(DataImportService::class);
		$this->userManager = $container->get(IUserManager::class);

		self::ensureSchemaExists($container->get(IDBConnection::class));
	}

	protected function tearDown(): void {
		foreach ($this->payloadFiles as $payloadFile) {
			@unlink($payloadFile);
		}

		foreach ($this->createdUserIds as $userId) {
			foreach ($this->fieldValueMapper->findByUserUid($userId) as $value) {
				$this->fieldValueMapper->delete($value);
			}
		}

		foreach ($this->createdFieldKeys as $fieldKey) {
			$definition = $this->fieldDefinitionMapper->findByFieldKey($fieldKey);
			if ($definition instanceof FieldDefinition) {
				$this->fieldDefinitionMapper->delete($definition);
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

	public function testExecuteImportsValidPayloadIntoDatabase(): void {
		$userId = $this->createUser('pf_import_cli_valid_user');
		$fieldKey = 'pf_import_cli_valid_region';
		$payloadFile = $this->createPayloadFile([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => $fieldKey,
				'label' => 'Region',
				'type' => 'text',
				'admin_only' => false,
				'user_editable' => true,
				'user_visible' => true,
				'initial_visibility' => 'users',
				'sort_order' => 10,
				'active' => true,
				'created_at' => '2026-03-16T10:00:00+00:00',
				'updated_at' => '2026-03-16T10:00:00+00:00',
			]],
			'values' => [[
				'field_key' => $fieldKey,
				'user_uid' => $userId,
				'value' => ['value' => 'LATAM'],
				'current_visibility' => 'users',
				'updated_by_uid' => $userId,
				'updated_at' => '2026-03-16T10:30:00+00:00',
			]],
		]);

		$tester = new CommandTester(new Import($this->dataImportService));
		$exitCode = $tester->execute([
			'--input' => $payloadFile,
		]);

		self::assertSame(0, $exitCode);
		self::assertStringContainsString('Profile Fields data imported.', $tester->getDisplay());
		self::assertStringContainsString('Definitions: 1 created, 0 updated, 0 skipped.', $tester->getDisplay());
		self::assertStringContainsString('Values: 1 created, 0 updated, 0 skipped.', $tester->getDisplay());

		$definition = $this->fieldDefinitionMapper->findByFieldKey($fieldKey);
		self::assertInstanceOf(FieldDefinition::class, $definition);
		self::assertSame('Region', $definition->getLabel());

		$value = $this->fieldValueMapper->findByFieldDefinitionIdAndUserUid($definition->getId(), $userId);
		self::assertInstanceOf(FieldValue::class, $value);
		self::assertSame('{"value":"LATAM"}', $value->getValueJson());
	}

	public function testExecuteDryRunDoesNotPersistValidatedPayload(): void {
		$userId = $this->createUser('pf_import_cli_dry_run_user');
		$fieldKey = 'pf_import_cli_dry_run_alias';
		$payloadFile = $this->createPayloadFile([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => $fieldKey,
				'label' => 'Alias',
				'type' => 'text',
				'admin_only' => false,
				'user_editable' => true,
				'user_visible' => true,
				'initial_visibility' => 'private',
				'sort_order' => 20,
				'active' => true,
			]],
			'values' => [[
				'field_key' => $fieldKey,
				'user_uid' => $userId,
				'value' => ['value' => 'ops-latam'],
				'current_visibility' => 'private',
				'updated_by_uid' => $userId,
				'updated_at' => '2026-03-16T11:00:00+00:00',
			]],
		]);

		$tester = new CommandTester(new Import($this->dataImportService));
		$exitCode = $tester->execute([
			'--input' => $payloadFile,
			'--dry-run' => true,
		]);

		self::assertSame(0, $exitCode);
		self::assertStringContainsString('Profile Fields data import dry-run completed.', $tester->getDisplay());
		self::assertNull($this->fieldDefinitionMapper->findByFieldKey($fieldKey));
	}

	public function testExecuteFailsValidationWithoutPersistingData(): void {
		$fieldKey = 'pf_import_cli_invalid_user';
		$payloadFile = $this->createPayloadFile([
			'schema_version' => 1,
			'definitions' => [[
				'field_key' => $fieldKey,
				'label' => 'Specialty',
				'type' => 'text',
				'admin_only' => false,
				'user_editable' => true,
				'user_visible' => true,
				'initial_visibility' => 'users',
				'sort_order' => 30,
				'active' => true,
			]],
			'values' => [[
				'field_key' => $fieldKey,
				'user_uid' => 'pf_import_cli_missing_user',
				'value' => ['value' => 'support'],
				'current_visibility' => 'users',
				'updated_by_uid' => 'pf_import_cli_missing_user',
				'updated_at' => '2026-03-16T11:30:00+00:00',
			]],
		]);

		$tester = new CommandTester(new Import($this->dataImportService));
		$exitCode = $tester->execute([
			'--input' => $payloadFile,
		]);

		self::assertSame(1, $exitCode);
		self::assertStringContainsString('Import validation failed.', $tester->getDisplay());
		self::assertStringContainsString('values[0].user_uid does not exist in destination instance', $tester->getDisplay());
		self::assertNull($this->fieldDefinitionMapper->findByFieldKey($fieldKey));
	}

	private function createUser(string $userId): string {
		if ($this->userManager->get($userId) === null) {
			$this->userManager->createUser($userId, $userId);
			$this->createdUserIds[$userId] = $userId;
		}

		return $userId;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function createPayloadFile(array $payload): string {
		$payloadFile = tempnam(sys_get_temp_dir(), 'profile-fields-cli-import-');
		if ($payloadFile === false) {
			throw new \RuntimeException('Failed to create temporary payload file');
		}

		file_put_contents($payloadFile, json_encode($payload, JSON_THROW_ON_ERROR));
		$this->payloadFiles[] = $payloadFile;

		foreach (($payload['definitions'] ?? []) as $definition) {
			if (is_array($definition) && is_string($definition['field_key'] ?? null)) {
				$this->createdFieldKeys[] = $definition['field_key'];
			}
		}

		return $payloadFile;
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
