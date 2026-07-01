<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks reachability of the configured Flowable engine.
 */
#[AsCommand(name: 'flowable:health', description: 'Check the configured Flowable engine health')]
final class HealthCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addApiConfigurationOption();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $io->writeln(sprintf('<comment>endpoint:</comment> %s', $client->getEndpoint()));
            $health = $client->getHealthInfo();
            $io->writeln((string) json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($health['reachable'] ?? false) === true ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
