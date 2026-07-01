<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/process_instances.
 */
#[AsCommand(name: 'flowable:process-instances:create', description: 'Start a Flowable process instance by definition key or id')]
final class ProcessInstanceCreateCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addWriteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readInput($input, $this->schemaPath('FlowProcessInstance', 'create'));
            $actor = $this->requireActingUser($input);
            $client = $this->client($input);

            $payload = [];
            foreach (['processDefinitionId', 'processDefinitionKey', 'businessKey'] as $key) {
                if (isset($body[$key])) {
                    $payload[$key] = $body[$key];
                }
            }
            // startUserId is kept for engines that honour it; the za7 actor is
            // also recorded as the startedBy variable since Flowable 7.2 ignores it.
            $variables = $this->variableMapper->toFlowable($body['variables'] ?? null);
            $variables[] = ['name' => 'startedBy', 'value' => $actor, 'type' => 'string'];
            $payload['variables'] = $variables;
            $payload['startUserId'] = $actor;

            return $this->renderItem($io, $client->startProcessInstance($payload));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
