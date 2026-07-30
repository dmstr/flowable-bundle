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
use Dmstr\Flowable\State\FlowEventDefinitionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less, read-only pass-through resource for Flowable event
 * definitions — the .event resources of an event registry deployment (see
 * FlowEventDeployment).
 *
 * An event definition describes the payload contract of a business event and
 * its correlation keys; a channel definition (FlowChannelDefinition) describes
 * how events reach the engine. Sending an actual event instance is
 * POST /event_instances (FlowEventInstance), which needs one of each.
 *
 * Deploying is done through FlowEventDeployment, so there are no write
 * operations here. Read is open to ROLE_USER.
 */
#[ApiResource(
    shortName: 'FlowEventDefinition',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Event definitions'],
    operations: [
        new GetCollection(
            uriTemplate: '/event_definitions',
            provider: FlowEventDefinitionProvider::class,
            parameters: [
                'key' => new QueryParameter(description: 'Filter by event definition key'),
                'keyLike' => new QueryParameter(description: 'Filter by key (SQL LIKE pattern)'),
                'name' => new QueryParameter(description: 'Filter by name'),
                'nameLike' => new QueryParameter(description: 'Filter by name (SQL LIKE pattern)'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'categoryLike' => new QueryParameter(description: 'Filter by category (SQL LIKE pattern)'),
                'categoryNotEquals' => new QueryParameter(description: 'Exclude a category'),
                'version' => new QueryParameter(description: 'Filter by exact version'),
                'latest' => new QueryParameter(description: 'Keep only the latest version per key'),
                'resourceName' => new QueryParameter(description: 'Filter by deployment resource name'),
                'deploymentId' => new QueryParameter(description: 'Filter by event deployment id'),
                'eventDeployment' => new QueryParameter(description: 'Filter by event deployment (IRI or id) — relation filter'),
                'parentDeploymentId' => new QueryParameter(description: 'Filter by parent deployment id'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/event_definitions/{id}',
            provider: FlowEventDefinitionProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_event_definition:read']],
    openapi: new Operation(tags: ['Flowable/Events']),
)]
final class FlowEventDefinition
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_event_definition:read'])]
    public ?string $id = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $key = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $name = null;

    #[Groups(['flow_event_definition:read'])]
    public ?int $version = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $description = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $category = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $url = null;

    /** Name of the .event resource inside the deployment. */
    #[Groups(['flow_event_definition:read'])]
    public ?string $resourceName = null;

    /** Engine URL of the raw .event resource content. */
    #[Groups(['flow_event_definition:read'])]
    public ?string $resource = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $deploymentId = null;

    /** Navigable IRI link to the event deployment this definition belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_event_definition:read'])]
    public ?FlowEventDeployment $eventDeployment = null;

    #[Groups(['flow_event_definition:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_event_definition:raw group. */
    #[Groups(['flow_event_definition:raw'])]
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
