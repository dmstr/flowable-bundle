<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/event_instances.
 *
 * Sends an event straight into an inbound channel of the engine, with no message
 * broker in between — the CLI way to drive inbound event correlation while
 * modelling a process. The input is validated against the same schema as the
 * REST operation, so both reject a body that names no event definition or no
 * channel definition.
 */
#[AsCommand(name: 'flowable:events:instances:create', description: 'Send an event instance to an inbound channel of the event registry')]
final class EventInstanceCreateCommand extends AbstractFlowableCommand
{
    /** Body keys the engine's event-instance endpoint accepts. */
    private const PAYLOAD_KEYS = [
        'eventDefinitionId',
        'eventDefinitionKey',
        'channelDefinitionId',
        'channelDefinitionKey',
        'tenantId',
        'eventPayload',
    ];

    protected function configure(): void
    {
        $this->addWriteOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $body = $this->readInput($input, $this->schemaPath('FlowEventInstance', 'create'));
            $this->requireActingUser($input);
            $client = $this->client($input);

            $payload = [];
            foreach (self::PAYLOAD_KEYS as $key) {
                if (\array_key_exists($key, $body)) {
                    $payload[$key] = $body[$key];
                }
            }
            // An empty JSON object decodes to [] and would re-encode as an
            // array, which the engine rejects for eventPayload.
            if (($payload['eventPayload'] ?? null) === []) {
                $payload['eventPayload'] = new \stdClass();
            }

            $client->createEventInstance($payload);
            $io->success('Event instance sent.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
