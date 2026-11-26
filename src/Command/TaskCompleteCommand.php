<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/tasks/{id}/complete.
 */
#[AsCommand(name: 'flowable:tasks:complete', description: 'Complete a Flowable user task')]
final class TaskCompleteCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Task id to complete');
        $this->addWriteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readInput($input, $this->schemaPath('FlowTask', 'complete'));
            $actor = $this->requireActingUser($input);
            $client = $this->client($input);

            $payload = ['action' => 'complete'];
            $variables = $this->variableMapper->toFlowable($body['variables'] ?? null);
            $variables[] = ['name' => 'triggeredBy', 'value' => $actor, 'type' => 'string'];
            $payload['variables'] = $variables;

            $taskId = (string) $input->getArgument('id');
            $this->audited(
                'task.complete',
                $actor,
                ['task' => $taskId],
                static fn () => $client->completeTask($taskId, $payload),
            );
            $io->success('Task completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): audit entries via cli)
