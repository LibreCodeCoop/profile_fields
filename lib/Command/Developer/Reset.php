<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Command\Developer;

use OCA\ProfileFields\AppInfo\Application;
use OCP\IDBConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Reset extends Command {
	public function __construct(
		private IDBConnection $connection,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('profile_fields:developer:reset')
			->setDescription('Clean Profile Fields data used by integration tests')
			->addOption(
				name: 'all',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset values and definitions',
			)
			->addOption(
				name: 'values',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset stored profile field values',
			)
			->addOption(
				name: 'definitions',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Reset profile field definitions and their values',
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$resetAll = (bool)$input->getOption('all');
		$resetValues = $resetAll || (bool)$input->getOption('values') || (bool)$input->getOption('definitions');
		$resetDefinitions = $resetAll || (bool)$input->getOption('definitions');

		if (!$resetValues && !$resetDefinitions) {
			$output->writeln('<error>Please inform what you want to reset.</error>');
			$output->writeln('<error>Use --all, --values or --definitions.</error>');
			return self::FAILURE;
		}

		if ($resetValues) {
			$this->deleteTable('profile_fields_values');
		}

		if ($resetDefinitions) {
			$this->deleteTable('profile_fields_definitions');
			$this->deleteWorkflowTables();
			$this->deleteProfileFieldNotifications();
		}

		$output->writeln('<info>Profile Fields data reset complete.</info>');
		return self::SUCCESS;
	}

	private function deleteWorkflowTables(): void {
		foreach (['flow_checks', 'flow_operations', 'flow_operations_scope'] as $tableName) {
			$this->deleteTable($tableName);
		}
	}

	private function deleteProfileFieldNotifications(): void {
		if (!$this->connection->tableExists('notifications')) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->delete('notifications')
			->where($query->expr()->eq('app', $query->createNamedParameter(Application::APP_ID)))
			->executeStatement();
	}

	private function deleteTable(string $tableName): void {
		if (!$this->connection->tableExists($tableName)) {
			return;
		}

		$query = $this->connection->getQueryBuilder();
		$query->delete($tableName)->executeStatement();
	}
}
