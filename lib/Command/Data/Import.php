<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and LibreCode contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Command\Data;

use JsonException;
use OCA\ProfileFields\Service\DataImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Import extends Command {
	public function __construct(
		private DataImportService $dataImportService,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('profile_fields:data:import')
			->setDescription('Import persisted Profile Fields definitions and values from a JSON payload')
			->addOption(
				name: 'input',
				shortcut: 'i',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Read the JSON import payload from a file',
			)
			->addOption(
				name: 'dry-run',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Validate the payload and report the import summary without persisting data',
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$sourcePath = $input->getOption('input');
		if (!is_string($sourcePath) || $sourcePath === '') {
			$output->writeln('<error>Please provide --input with a JSON file path.</error>');
			return self::FAILURE;
		}

		$rawPayload = @file_get_contents($sourcePath);
		if ($rawPayload === false) {
			$output->writeln(sprintf('<error>Could not read import payload from %s.</error>', $sourcePath));
			return self::FAILURE;
		}

		try {
			$decodedPayload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			$output->writeln('<error>Failed to decode import payload JSON.</error>');
			$output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
			return self::FAILURE;
		}

		if (!is_array($decodedPayload)) {
			$output->writeln('<error>Import payload must decode to a JSON object.</error>');
			return self::FAILURE;
		}

		try {
			$summary = $this->dataImportService->import($decodedPayload, (bool)$input->getOption('dry-run'));
		} catch (\Throwable $throwable) {
			$output->writeln('<error>Import validation failed.</error>');
			$output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
			return self::FAILURE;
		}

		$output->writeln((bool)$input->getOption('dry-run')
			? '<info>Profile Fields data import dry-run completed.</info>'
			: '<info>Profile Fields data imported.</info>');
		$output->writeln(sprintf(
			'<info>Definitions: %d created, %d updated, %d skipped.</info>',
			$summary['created_definitions'],
			$summary['updated_definitions'],
			$summary['skipped_definitions'],
		));
		$output->writeln(sprintf(
			'<info>Values: %d created, %d updated, %d skipped.</info>',
			$summary['created_values'],
			$summary['updated_values'],
			$summary['skipped_values'],
		));

		return self::SUCCESS;
	}
}