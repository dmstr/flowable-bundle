<?php
// file generated with AI assistance: Claude Code - 2026-06-22 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/executions/{id}/trigger.
 *
 * The id is a child/leaf execution id (find it via `flowable:executions
 * --process-instance <id>`), never a process-instance id.
 */
#[AsCommand(name: 'flowable:executions:trigger', description: 'Trigger a waiting execution by execution id')]
final class ExecutionTriggerCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Execution id to trigger (a waiting child execution)');
        $this->addWriteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readInput($input, $this->schemaPath('FlowExecution', 'trigger'));
            $actor = $this->requireActingUser($input);
            $client = $this->client($input);

            $payload = ['action' => 'trigger'];
            $variables = $this->variableMapper->toFlowable($body['variables'] ?? null);
            $variables[] = ['name' => 'triggeredBy', 'value' => $actor, 'type' => 'string'];
            $payload['variables'] = $variables;

            $client->triggerExecution((string) $input->getArgument('id'), $payload);
            $io->success('Execution triggered.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
