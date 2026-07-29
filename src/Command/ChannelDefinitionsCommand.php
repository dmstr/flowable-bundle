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
 * Mirrors GET /api/flowable/channel_definitions (and the item GET).
 *
 * Handy before sending an event: POST /event_instances needs a channel
 * definition key, and --inbound narrows the list to the channels that can
 * receive one.
 */
#[AsCommand(name: 'flowable:events:channels', description: 'List Flowable channel definitions, or show one by id')]
final class ChannelDefinitionsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Channel definition id (shows a single definition)');
        $this->addApiConfigurationOption();
        $this->addOption('latest', 'l', InputOption::VALUE_NONE, 'Keep only the latest version per key');
        $this->addOption('inbound', null, InputOption::VALUE_NONE, 'Keep only inbound channels');
        $this->addOption('outbound', null, InputOption::VALUE_NONE, 'Keep only outbound channels');
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findChannelDefinition((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if ($input->getOption('latest')) {
                $query['latest'] = 'true';
            }
            if ($input->getOption('inbound')) {
                $query['onlyInbound'] = 'true';
            }
            if ($input->getOption('outbound')) {
                $query['onlyOutbound'] = 'true';
            }

            return $this->renderEnvelope(
                $io,
                $client->listChannelDefinitions($query),
                ['id', 'key', 'name', 'version', 'type', 'implementation'],
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
