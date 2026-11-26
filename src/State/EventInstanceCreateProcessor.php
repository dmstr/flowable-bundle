<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Sends an event instance into the event registry (POST /event_instances).
 *
 * The event goes straight to an inbound channel inside the engine — no message
 * broker is involved — which is what makes inbound event correlation testable
 * in a plain flowable-rest container.
 *
 * Only the keys the engine knows are forwarded, so a stray property cannot end
 * up in the engine body. Unlike the other write processors no actor variable is
 * attached: an event's payload is defined by its event definition, and adding a
 * field to it would break the payload contract — the acting user is recorded on
 * the audit channel only.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class EventInstanceCreateProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public const string INPUT_SCHEMA = 'FlowEventInstance/create.input.json';

    /** Body keys the engine's event-instance endpoint accepts. */
    private const PAYLOAD_KEYS = [
        'eventDefinitionId',
        'eventDefinitionKey',
        'channelDefinitionId',
        'channelDefinitionKey',
        'tenantId',
        'eventPayload',
    ];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validatedBody($context);
        $client = $this->client($body, $context);

        $payload = [];
        foreach (self::PAYLOAD_KEYS as $key) {
            if (\array_key_exists($key, $body)) {
                $payload[$key] = $body[$key];
            }
        }

        // json_decode(..., true) collapses an empty JSON object to [], which
        // would re-encode as [] and be rejected — the engine insists on an
        // object for eventPayload.
        if (($payload['eventPayload'] ?? null) === []) {
            $payload['eventPayload'] = new \stdClass();
        }

        $this->audited(
            'event_instance.create',
            $context,
            [
                'eventDefinition' => $body['eventDefinitionKey'] ?? $body['eventDefinitionId'] ?? null,
                'channelDefinition' => $body['channelDefinitionKey'] ?? $body['channelDefinitionId'] ?? null,
            ],
            static fn () => $client->createEventInstance($payload),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (input file declared once in INPUT_SCHEMA, read by validatedBody() and the MCP tool)
