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
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowEventInstance', 'create'));
        $client = $this->client($body);

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

        $client->createEventInstance($payload);
        $this->audit('event_instance.create', [
            'eventDefinition' => $body['eventDefinitionKey'] ?? $body['eventDefinitionId'] ?? null,
            'channelDefinition' => $body['channelDefinitionKey'] ?? $body['channelDefinitionId'] ?? null,
        ]);

        return null;
    }
}
