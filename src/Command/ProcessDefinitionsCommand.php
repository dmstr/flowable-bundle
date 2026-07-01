<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/process_definitions (and the item GET).
 */
#[AsCommand(name: 'flowable:process-definitions', description: 'List Flowable process definitions, or show one by id')]
final class ProcessDefinitionsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Process definition id (shows a single definition)');
        $this->addApiConfigurationOption();
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
        $this->addOption('latest', null, InputOption::VALUE_NONE, 'Keep only the latest version per key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findProcessDefinition((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if ($input->getOption('latest')) {
                $query['latest'] = 'true';
            }

            return $this->renderEnvelope($io, $client->listProcessDefinitions($query), ['id', 'key', 'version', 'name']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
