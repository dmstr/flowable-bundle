<?php
// file generated with AI assistance: Claude Code - 2026-06-22 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors GET /api/flowable/executions (and the item GET).
 *
 * Use --process-instance to list the executions of a running instance and find
 * the waiting child execution (activityId set) to pass to executions:trigger.
 */
#[AsCommand(name: 'flowable:executions', description: 'List Flowable runtime executions, or show one by id')]
final class ExecutionsCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Execution id (shows a single execution)');
        $this->addApiConfigurationOption();
        $this->addOption('process-instance', 'p', InputOption::VALUE_REQUIRED, 'Filter by process instance id');
        $this->addOption('activity-id', 'a', InputOption::VALUE_REQUIRED, 'Filter by the BPMN activity id the execution waits at');
        $this->addOption('size', 's', InputOption::VALUE_REQUIRED, 'Page size for listing', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $client = $this->client($input);
            $id = $input->getArgument('id');
            if ($id !== null) {
                return $this->renderItem($io, $client->findExecution((string) $id));
            }

            $query = ['size' => (int) $input->getOption('size')];
            if (($pi = $input->getOption('process-instance')) !== null) {
                $query['processInstanceId'] = (string) $pi;
            }
            if (($activity = $input->getOption('activity-id')) !== null) {
                $query['activityId'] = (string) $activity;
            }
            $envelope = $client->listExecutions($query);

            return $this->renderEnvelope($io, $envelope, ['id', 'parentId', 'processInstanceId', 'activityId', 'suspended']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
