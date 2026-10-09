<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\EventInstanceCreateProcessor;

/**
 * Doctrine-less pass-through resource for sending event instances into the
 * Flowable event registry — write-only, POST /event_instances.
 *
 * This is the reason the event registry is worth passing through at all: the
 * operation hands an event straight to an inbound channel INSIDE the engine,
 * with no message broker in between. Inbound event correlation (BPMN event
 * sub-processes, receive events, boundary events) is therefore usable and
 * verifiable without running Kafka or RabbitMQ. The outbound direction
 * (engine → broker) is a separate, broker-dependent concern and is not covered
 * by this bundle.
 *
 * The body needs an event definition reference (eventDefinitionKey or
 * eventDefinitionId) AND a channel definition reference (channelDefinitionKey
 * or channelDefinitionId) — the channel is what turns the payload into a
 * correlated event, so the engine rejects a body without one. Sending events
 * drives process state and therefore requires ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowEventInstance',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Event instances'],
    operations: [
        new Post(
            uriTemplate: '/event_instances',
            name: 'flow_event_instance_create',
            description: 'Send an event instance to an inbound channel of the event registry (no message broker involved)',
            processor: EventInstanceCreateProcessor::class,
            deserialize: false,
            validate: false,
            // The engine answers 204 No Content and returns no representation of
            // the sent event, so there is nothing to serialise back.
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(tags: ['Flowable/Events']),
        ),
    ],
    mcp: [
        'flowable_events_send' => new McpTool(
            name: 'flowable_events_send',
            description: 'Send an event to an inbound channel of the event registry; needs eventDefinitionKey or eventDefinitionId, channelDefinitionKey or channelDefinitionId, and eventPayload as an object. Returns null on success; failures are tool errors.',
            annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
            meta: ['de.dmstr/tag' => 'Flowable/Events'],
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            processor: EventInstanceCreateProcessor::class,
            read: false,
            structuredContent: false,
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowEventInstance/create.input.json']],
        ),
    ],
    security: "is_granted('ROLE_USER')",
    openapi: new Operation(tags: ['Flowable/Events']),
)]
final class FlowEventInstance
{
    /**
     * Always null: the resource exists only to carry the POST operation, and
     * the engine hands back no event id. Declared because API Platform expects
     * every resource to name an identifier property.
     */
    #[ApiProperty(identifier: true)]
    public ?string $id = null;
}
// - revised 2026-10-08 (MCP tool flowable_events_send)
// - revised 2026-10-09 (MCP: destructiveHint)
