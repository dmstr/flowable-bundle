<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\FlowChannelDefinitionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less, read-only pass-through resource for Flowable channel
 * definitions — the .channel resources of an event registry deployment (see
 * FlowEventDeployment).
 *
 * A channel definition says how events travel: $type is its direction
 * ("inbound" / "outbound") and $implementation the transport ("jms", "kafka",
 * "rabbit" and — the interesting one here — the broker-less in-engine channel
 * that POST /event_instances writes to. Inbound event correlation is therefore
 * exercisable without any broker; outbound delivery is not, since that leaves
 * the engine.
 *
 * Deploying is done through FlowEventDeployment, so there are no write
 * operations here. Read is open to ROLE_USER.
 */
#[ApiResource(
    shortName: 'FlowChannelDefinition',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Channel definitions'],
    operations: [
        new GetCollection(
            uriTemplate: '/channel_definitions',
            provider: FlowChannelDefinitionProvider::class,
            parameters: [
                'key' => new QueryParameter(description: 'Filter by channel definition key'),
                'keyLike' => new QueryParameter(description: 'Filter by key (SQL LIKE pattern)'),
                'name' => new QueryParameter(description: 'Filter by name'),
                'nameLike' => new QueryParameter(description: 'Filter by name (SQL LIKE pattern)'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'categoryLike' => new QueryParameter(description: 'Filter by category (SQL LIKE pattern)'),
                'categoryNotEquals' => new QueryParameter(description: 'Exclude a category'),
                'version' => new QueryParameter(description: 'Filter by exact version'),
                'latest' => new QueryParameter(description: 'Keep only the latest version per key'),
                'implementation' => new QueryParameter(description: 'Filter by transport implementation (jms, kafka, rabbit, ...)'),
                'onlyInbound' => new QueryParameter(description: 'Keep only inbound channels'),
                'onlyOutbound' => new QueryParameter(description: 'Keep only outbound channels'),
                'resourceName' => new QueryParameter(description: 'Filter by deployment resource name'),
                'deploymentId' => new QueryParameter(description: 'Filter by event deployment id'),
                'eventDeployment' => new QueryParameter(description: 'Filter by event deployment (IRI or id) — relation filter'),
                'parentDeploymentId' => new QueryParameter(description: 'Filter by parent deployment id'),
                'createTimeAfter' => new QueryParameter(description: 'Keep only channels created after this ISO-8601 timestamp'),
                'createTimeBefore' => new QueryParameter(description: 'Keep only channels created before this ISO-8601 timestamp'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/channel_definitions/{id}',
            provider: FlowChannelDefinitionProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_channel_definition:read']],
    openapi: new Operation(tags: ['Flowable/Events']),
)]
final class FlowChannelDefinition
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_channel_definition:read'])]
    public ?string $id = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $key = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $name = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?int $version = null;

    /** Channel direction: "inbound" or "outbound". */
    #[Groups(['flow_channel_definition:read'])]
    public ?string $type = null;

    /** Transport implementation, e.g. "jms", "kafka", "rabbit". */
    #[Groups(['flow_channel_definition:read'])]
    public ?string $implementation = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $createTime = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $description = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $category = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $url = null;

    /** Name of the .channel resource inside the deployment. */
    #[Groups(['flow_channel_definition:read'])]
    public ?string $resourceName = null;

    /** Engine URL of the raw .channel resource content. */
    #[Groups(['flow_channel_definition:read'])]
    public ?string $resource = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $deploymentId = null;

    /** Navigable IRI link to the event deployment this definition belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_channel_definition:read'])]
    public ?FlowEventDeployment $eventDeployment = null;

    #[Groups(['flow_channel_definition:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_channel_definition:raw group. */
    #[Groups(['flow_channel_definition:raw'])]
    public ?array $raw = null;

    /** Shallow instance carrying only the identifier, for IRI generation. */
    public static function reference(string $id): self
    {
        $self = new self();
        $self->id = $id;

        return $self;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->key = $data['key'] ?? null;
        $self->name = $data['name'] ?? null;
        $self->version = isset($data['version']) ? (int) $data['version'] : null;
        $self->type = $data['type'] ?? null;
        $self->implementation = $data['implementation'] ?? null;
        $self->createTime = $data['createTime'] ?? null;
        $self->description = $data['description'] ?? null;
        $self->category = $data['category'] ?? null;
        $self->url = $data['url'] ?? null;
        $self->resourceName = $data['resourceName'] ?? null;
        $self->resource = $data['resource'] ?? null;
        $self->deploymentId = $data['deploymentId'] ?? null;
        if ($self->deploymentId !== null) {
            $self->eventDeployment = FlowEventDeployment::reference($self->deploymentId);
        }
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
