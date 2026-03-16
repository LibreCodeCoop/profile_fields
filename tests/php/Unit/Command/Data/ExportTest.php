<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Command\Data;

use OCA\ProfileFields\Command\Data\Export;
use OCA\ProfileFields\Db\FieldDefinition;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Service\FieldDefinitionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ExportTest extends TestCase {
	private FieldDefinitionService&MockObject $fieldDefinitionService;
	private FieldValueMapper&MockObject $fieldValueMapper;
	private Export $command;

	protected function setUp(): void {
		parent::setUp();

		$this->fieldDefinitionService = $this->createMock(FieldDefinitionService::class);
		$this->fieldValueMapper = $this->createMock(FieldValueMapper::class);
		$this->command = new Export($this->fieldDefinitionService, $this->fieldValueMapper);
	}

	public function testExecuteExportsDefinitionsAndValuesAsJson(): void {
		$definition = new FieldDefinition();
		$definition->setId(7);
		$definition->setFieldKey('cost_center');
		$definition->setLabel('Cost center');
		$definition->setType('text');
		$definition->setAdminOnly(false);
		$definition->setUserEditable(true);
		$definition->setUserVisible(true);
		$definition->setInitialVisibility('users');
		$definition->setSortOrder(3);
		$definition->setActive(true);
		$definition->setCreatedAt(new \DateTime('2026-03-01T12:00:00+00:00'));
		$definition->setUpdatedAt(new \DateTime('2026-03-02T12:00:00+00:00'));

		$value = new FieldValue();
		$value->setId(11);
		$value->setFieldDefinitionId(7);
		$value->setUserUid('alice');
		$value->setValueJson('{"value":"finance"}');
		$value->setCurrentVisibility('users');
		$value->setUpdatedByUid('admin');
		$value->setUpdatedAt(new \DateTime('2026-03-03T12:00:00+00:00'));

		$this->fieldDefinitionService->expects($this->once())
			->method('findAllOrdered')
			->willReturn([$definition]);

		$this->fieldValueMapper->expects($this->once())
			->method('findAllOrdered')
			->willReturn([$value]);

		$tester = new CommandTester($this->command);

		$exitCode = $tester->execute([]);

		self::assertSame(0, $exitCode);

		/** @var array{exported_at: string, definitions: list<array<string, mixed>>, values: list<array<string, mixed>>} $payload */
		$payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

		self::assertSame(1, $payload['schema_version']);
		self::assertArrayHasKey('exported_at', $payload);
		self::assertSame('cost_center', $payload['definitions'][0]['field_key']);
		self::assertSame('cost_center', $payload['values'][0]['field_key']);
		self::assertSame('alice', $payload['values'][0]['user_uid']);
		self::assertSame(['value' => 'finance'], $payload['values'][0]['value']);
	}
}
