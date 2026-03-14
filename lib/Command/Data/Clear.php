<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Command\Data;

use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Clear extends Command {
	public function __construct(
		private IDBConnection $connection,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('profile_fields:data:clear')
			->setDescription('Clear persisted Profile Fields data')
			->addOption(
				name: 'all',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Clear values and definitions',
			)
			->addOption(
				name: 'values',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Clear stored field values only',
			)
			->addOption(
				name: 'definitions',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Clear field definitions and their values',
			)
			->addOption(
				name: 'force',
				shortcut: 'f',
				mode: InputOption::VALUE_NONE,
				description: 'Apply the deletion without asking for confirmation elsewhere',
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$clearAll = (bool)$input->getOption('all');
		$clearValues = $clearAll || (bool)$input->getOption('values') || (bool)$input->getOption('definitions');
		$clearDefinitions = $clearAll || (bool)$input->getOption('definitions');

		if (!$clearValues && !$clearDefinitions) {
			$output->writeln('<error>Please inform what you want to clear.</error>');
			$output->writeln('<error>Use --all, --values or --definitions.</error>');
			return self::FAILURE;
		}

		if (!(bool)$input->getOption('force')) {
			$output->writeln('<error>Refusing to clear persisted data without --force.</error>');
			return self::FAILURE;
		}

		if ($clearValues) {
			$this->deleteTable('profile_fields_values');
		}

		if ($clearDefinitions) {
			$this->deleteTable('profile_fields_definitions');
		}

		$output->writeln('<info>Profile Fields persisted data cleared.</info>');
		return self::SUCCESS;
	}

	private function deleteTable(string $tableName): void {
		if (!$this->connection->tableExists($tableName)) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->delete($tableName)->executeStatement();
	}
}
