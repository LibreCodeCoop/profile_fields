<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\ProfileFields\Command\Data;

use JsonException;
use OCA\ProfileFields\Db\FieldValue;
use OCA\ProfileFields\Db\FieldValueMapper;
use OCA\ProfileFields\Service\FieldDefinitionService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Export extends Command {
	public function __construct(
		private FieldDefinitionService $fieldDefinitionService,
		private FieldValueMapper $fieldValueMapper,
	) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('profile_fields:data:export')
			->setDescription('Export persisted Profile Fields definitions and values as JSON')
			->addOption(
				name: 'output',
				shortcut: 'o',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Write the JSON export to a file instead of stdout',
			)
			->addOption(
				name: 'pretty',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Pretty-print the JSON output',
			);
	}

	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$payload = [
			'exported_at' => gmdate(DATE_ATOM),
			'definitions' => array_map(
				static fn ($definition): array => $definition->jsonSerialize(),
				$this->fieldDefinitionService->findAllOrdered(),
			),
			'values' => array_map(
				fn (FieldValue $value): array => $this->serializeValue($value),
				$this->fieldValueMapper->findAllOrdered(),
			),
		];

		try {
			$json = json_encode(
				$payload,
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ((bool)$input->getOption('pretty') ? JSON_PRETTY_PRINT : 0),
			);
		} catch (JsonException $exception) {
			$output->writeln('<error>Failed to encode export payload.</error>');
			$output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
			return self::FAILURE;
		}

		$targetPath = $input->getOption('output');
		if (is_string($targetPath) && $targetPath !== '') {
			if (@file_put_contents($targetPath, $json . PHP_EOL) === false) {
				$output->writeln(sprintf('<error>Could not write export to %s.</error>', $targetPath));
				return self::FAILURE;
			}

			$output->writeln(sprintf('<info>Profile Fields data exported to %s.</info>', $targetPath));
			return self::SUCCESS;
		}

		$output->writeln($json);
		return self::SUCCESS;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function serializeValue(FieldValue $value): array {
		try {
			$decodedValue = json_decode($value->getValueJson(), true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			throw new \RuntimeException('Failed to decode stored field value JSON.', 0, $exception);
		}

		return [
			'id' => $value->getId(),
			'field_definition_id' => $value->getFieldDefinitionId(),
			'user_uid' => $value->getUserUid(),
			'value' => $decodedValue,
			'current_visibility' => $value->getCurrentVisibility(),
			'updated_by_uid' => $value->getUpdatedByUid(),
			'updated_at' => $value->getUpdatedAt()->format(DATE_ATOM),
		];
	}
}
