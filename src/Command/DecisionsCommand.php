<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/decisions (and the item GET).
 */
#[AsCommand(name: 'flowable:dmn:decisions', description: 'List Flowable DMN decisions, or show one by id')]
final class DecisionsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Decision id (shows a single decision)');
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
                return $this->renderItem($io, $client->findDecision((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if ($input->getOption('latest')) {
                $query['latest'] = 'true';
            }

            return $this->renderEnvelope($io, $client->listDecisions($query), ['id', 'key', 'name', 'version', 'deploymentId']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
