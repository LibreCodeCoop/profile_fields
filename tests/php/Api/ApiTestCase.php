<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Api;

use ByJG\ApiTools\ApiRequester;
use ByJG\ApiTools\Base\Schema;
use OCA\ProfileFields\AppInfo\Application;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldDefinitionMapper;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Enum\FieldEditPolicy;
use OCA\ProfileFields\Enum\FieldExposurePolicy;
use OCA\ProfileFields\Migration\Version1000Date20260309120000;
use OCA\ProfileFields\Service\FieldDefinitionService;
use OCA\ProfileFields\Service\FieldValueService;
use OCP\App\IAppManager;
use OCP\DB\ISchemaWrapper;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Server;
use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase {
	private static bool $appWasEnabled;
	private static string $originalInstalledVersion;
	protected Schema $schema;
	protected FieldDefinitionMapper $fieldDefinitionMapper;
	protected FieldValueMapper $fieldValueMapper;
	protected FieldDefinitionService $fieldDefinitionService;
	protected FieldValueService $fieldValueService;
	protected IUserManager $userManager;
	protected IGroupManager $groupManager;
	/** @var array<int, FieldDefinition> */
	private array $createdDefinitions = [];
	/** @var array<int, FieldValue> */
	private array $createdValues = [];
	/** @var array<string, string> */
	private array $createdUserIds = [];

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$appManager = Server::get(IAppManager::class);
		$config = Server::get(IAppConfig::class);
		self::$originalInstalledVersion = $config->getValueString(Application::APP_ID, 'installed_version', '');

		if (self::$originalInstalledVersion === '') {
			$config->setValueString(
				Application::APP_ID,
				'installed_version',
				$appManager->getAppVersion(Application::APP_ID, false),
			);
		}

		self::ensureSchemaExists();
		self::$appWasEnabled = $appManager->isEnabledForAnyone(Application::APP_ID);

		if (!self::$appWasEnabled) {
			$appManager->enableApp(Application::APP_ID);
		}

		$appManager->loadApp(Application::APP_ID);
		self::syncHttpAppState(true);
	}

	public static function tearDownAfterClass(): void {
		$appManager = Server::get(IAppManager::class);
		$config = Server::get(IAppConfig::class);
		if (!self::$appWasEnabled) {
			$appManager->disableApp(Application::APP_ID);
			self::syncHttpAppState(false);
		}

		if (self::$originalInstalledVersion === '') {
			$config->deleteKey(Application::APP_ID, 'installed_version');
		}

		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();

		$baseUrl = $this->resolveApiBaseUrl();

		/** @var array<string, mixed> $data */
		$data = json_decode((string)file_get_contents(__DIR__ . '/../../../openapi-full.json'), true, flags: JSON_THROW_ON_ERROR);
		$data['servers'] = [
			['url' => $baseUrl],
		];

		$this->schema = Schema::getInstance($data);
		$this->fieldDefinitionMapper = Server::get(FieldDefinitionMapper::class);
		$this->fieldValueMapper = Server::get(FieldValueMapper::class);
		$this->fieldDefinitionService = Server::get(FieldDefinitionService::class);
		$this->fieldValueService = Server::get(FieldValueService::class);
		$this->userManager = Server::get(IUserManager::class);
		$this->groupManager = Server::get(IGroupManager::class);
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

	protected function newApiRequester(): ApiRequester {
		return (new ApiRequester())
			->withSchema($this->schema)
			->withRequestHeader([
				'OCS-APIRequest' => 'true',
				'Accept' => 'application/json',
			]);
	}

	protected function withBasicAuth(ApiRequester $requester, string $userId, string $password): ApiRequester {
		return $requester->withRequestHeader([
			'Authorization' => 'Basic ' . base64_encode($userId . ':' . $password),
		]);
	}

	protected function createUser(string $userId, ?string $password = null): string {
		if ($this->userManager->get($userId) === null) {
			$this->userManager->createUser($userId, $password ?? $userId);
			$this->createdUserIds[$userId] = $userId;
		}

		return $userId;
	}

	protected function ensureAdminUser(string $userId, string $password): void {
		$this->createUser($userId, $password);

		$user = $this->userManager->get($userId);
		$adminGroup = $this->groupManager->get('admin');
		if ($user instanceof IUser && $adminGroup instanceof IGroup && !$adminGroup->inGroup($user)) {
			$adminGroup->addUser($user);
		}
	}

	protected function createDefinition(
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

	protected function createStoredValue(
		FieldDefinition $definition,
		string $userUid,
		array|string|int|float|bool|null $value,
		string $updatedByUid,
		?string $currentVisibility = null,
	): FieldValue {
		$storedValue = $this->fieldValueService->upsert($definition, $userUid, $value, $updatedByUid, $currentVisibility);
		$this->createdValues[$storedValue->getId()] = $storedValue;

		return $storedValue;
	}

	protected function uniqueFieldKey(string $prefix): string {
		return sprintf('%s_%d', $prefix, random_int(1000, 999999));
	}

	private function resolveApiBaseUrl(): string {
		$configuredBaseUrl = getenv('PROFILE_FIELDS_API_BASE_URL');
		if (is_string($configuredBaseUrl) && $configuredBaseUrl !== '') {
			return $configuredBaseUrl;
		}

		$defaultBaseUrl = 'http://nginx';
		$defaultHost = parse_url($defaultBaseUrl, PHP_URL_HOST);
		if (is_string($defaultHost) && gethostbyname($defaultHost) !== $defaultHost) {
			return $defaultBaseUrl;
		}

		$this->markTestSkipped('API contract tests require PROFILE_FIELDS_API_BASE_URL or a resolvable nginx host.');
	}

	private static function ensureSchemaExists(): void {
		$connection = Server::get(IDBConnection::class);
		if ($connection->tableExists('profile_fields_definitions') && $connection->tableExists('profile_fields_values')) {
			return;
		}

		$nullOutputClass = '\\OC\\Migration\\NullOutput';
		$schemaWrapperClass = '\\OC\\DB\\SchemaWrapper';

		if (!class_exists($nullOutputClass) || !class_exists($schemaWrapperClass) || !method_exists($connection, 'getInner')) {
			throw new \RuntimeException('Expected ConnectionAdapter in API test setup');
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

	private static function syncHttpAppState(bool $enabled): void {
		$occPath = dirname(__DIR__, 5) . '/occ';
		$action = $enabled ? 'enable' : 'disable';
		$output = [];
		$exitCode = 0;

		exec(sprintf(
			'php %s app:%s %s 2>&1',
			escapeshellarg($occPath),
			$action,
			escapeshellarg(Application::APP_ID),
		), $output, $exitCode);

		if ($exitCode !== 0) {
			throw new \RuntimeException(sprintf(
				'Failed to %s %s for HTTP API tests: %s',
				$action,
				Application::APP_ID,
				implode("\n", $output),
			));
		}
	}
}
