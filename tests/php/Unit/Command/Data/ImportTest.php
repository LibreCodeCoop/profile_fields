<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Tests\Unit\Command\Data;

use OCA\ProfileFields\Command\Data\Import;
use OCA\ProfileFields\Service\DataImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportTest extends TestCase {
	private DataImportService&MockObject $dataImportService;
	private Import $command;

	protected function setUp(): void {
		parent::setUp();
		$this->dataImportService = $this->createMock(DataImportService::class);
		$this->command = new Import($this->dataImportService);
	}

	public function testExecuteRunsDryRunImportAndPrintsSummary(): void {
		$payloadFile = tempnam(sys_get_temp_dir(), 'profile-fields-import-');
		$this->assertNotFalse($payloadFile);
		file_put_contents($payloadFile, json_encode([
			'schema_version' => 1,
			'definitions' => [],
			'values' => [],
		], JSON_THROW_ON_ERROR));

		$this->dataImportService->expects($this->once())
			->method('import')
			->with([
				'schema_version' => 1,
				'definitions' => [],
				'values' => [],
			], true)
			->willReturn([
				'created_definitions' => 1,
				'updated_definitions' => 2,
				'skipped_definitions' => 3,
				'created_values' => 4,
				'updated_values' => 5,
				'skipped_values' => 6,
			]);

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute([
			'--input' => $payloadFile,
			'--dry-run' => true,
		]);

		self::assertSame(0, $exitCode);
		self::assertStringContainsString('Profile Fields data import dry-run completed.', $tester->getDisplay());
		self::assertStringContainsString('Definitions: 1 created, 2 updated, 3 skipped.', $tester->getDisplay());
		self::assertStringContainsString('Values: 4 created, 5 updated, 6 skipped.', $tester->getDisplay());

		@unlink($payloadFile);
	}

	public function testExecuteFailsForInvalidJsonInput(): void {
		$payloadFile = tempnam(sys_get_temp_dir(), 'profile-fields-import-invalid-');
		$this->assertNotFalse($payloadFile);
		file_put_contents($payloadFile, '{invalid-json');

		$this->dataImportService->expects($this->never())->method('import');

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute([
			'--input' => $payloadFile,
		]);

		self::assertSame(1, $exitCode);
		self::assertStringContainsString('Failed to decode import payload JSON.', $tester->getDisplay());

		@unlink($payloadFile);
	}
}