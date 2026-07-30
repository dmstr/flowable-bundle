<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/event_definitions (and the item GET).
 */
#[AsCommand(name: 'flowable:events:definitions', description: 'List Flowable event definitions, or show one by id')]
final class EventDefinitionsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Event definition id (shows a single definition)');
        $this->addApiConfigurationOption();
        $this->addOption('latest', 'l', InputOption::VALUE_NONE, 'Keep only the latest version per key');
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findEventDefinition((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if ($input->getOption('latest')) {
                $query['latest'] = 'true';
            }

            return $this->renderEnvelope($io, $client->listEventDefinitions($query), ['id', 'key', 'name', 'version', 'deploymentId']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
