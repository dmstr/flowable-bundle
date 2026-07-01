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
 * Mirrors GET /api/flowable/tasks (and the item GET).
 */
#[AsCommand(name: 'flowable:tasks', description: 'List Flowable tasks, or show one by id')]
final class TasksCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Task id (shows a single task)');
        $this->addApiConfigurationOption();
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findTask((string) $id));
            }

            $envelope = $client->listTasks(['size' => (int) $input->getOption('size')]);

            return $this->renderEnvelope($io, $envelope, ['id', 'name', 'assignee', 'processInstanceId', 'taskDefinitionKey']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
