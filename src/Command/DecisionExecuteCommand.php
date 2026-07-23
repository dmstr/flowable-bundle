<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/decisions/execute.
 *
 * Evaluates a decision from a JSON input (validated against the same schema as
 * the REST endpoint: decisionKey, inputVariables, optional singleResult).
 */
#[AsCommand(name: 'flowable:dmn:rule:execute', description: 'Evaluate a DMN decision by key against JSON input variables')]
final class DecisionExecuteCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addWriteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readInput($input, $this->schemaPath('FlowDecision', 'execute'));
            $client = $this->client($input);

            $payload = [
                'decisionKey' => (string) ($body['decisionKey'] ?? ''),
                'inputVariables' => $this->variableMapper->toFlowable($body['inputVariables'] ?? null),
            ];

            $response = ((bool) ($body['singleResult'] ?? false))
                ? $client->executeDecisionSingleResult($payload)
                : $client->executeDecision($payload);

            return $this->renderItem($io, $response);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
