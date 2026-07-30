<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use Dmstr\Flowable\State\EventDeploymentDeleteProcessor;
use Dmstr\Flowable\State\EventDeploymentUploadProcessor;
use Dmstr\Flowable\State\FlowEventDeploymentProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable event registry deployments.
 *
 * The event registry is a third engine repository next to the process engine's
 * and the DMN engine's, reached under /event-registry-api through the same
 * "flowable" ApiConfiguration. It holds event definitions (.event) and channel
 * definitions (.channel); the custom collection operation
 * POST /event_deployments/upload forwards a multipart resource to it.
 * Read is open to ROLE_USER; upload and delete require ROLE_FLOWABLE_ADMIN.
 *
 * Unlike the process and DMN repositories this one accepts ONLY .event and
 * .channel files — there is no .bar/.zip bundle support, so a deployment that
 * needs several resources is uploaded one file at a time.
 */
#[ApiResource(
    shortName: 'FlowEventDeployment',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Event deployments'],
    operations: [
        new GetCollection(
            uriTemplate: '/event_deployments',
            provider: FlowEventDeploymentProvider::class,
            parameters: [
                'name' => new QueryParameter(description: 'Filter by deployment name'),
                'nameLike' => new QueryParameter(description: 'Filter by deployment name (SQL LIKE pattern)'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'categoryNotEquals' => new QueryParameter(description: 'Exclude a category'),
                'parentDeploymentId' => new QueryParameter(description: 'Filter by parent deployment id'),
                'parentDeploymentIdLike' => new QueryParameter(description: 'Filter by parent deployment id (SQL LIKE pattern)'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'tenantIdLike' => new QueryParameter(description: 'Filter by tenant id (SQL LIKE pattern)'),
                'withoutTenantId' => new QueryParameter(description: 'Keep only deployments without a tenant'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/event_deployments/{id}',
            provider: FlowEventDeploymentProvider::class,
        ),
        new Post(
            uriTemplate: '/event_deployments/upload',
            name: 'flow_event_deployment_upload',
            description: 'Deploy a .event or .channel resource to the event registry',
            processor: EventDeploymentUploadProcessor::class,
            deserialize: false,
            validate: false,
            inputFormats: ['multipart' => ['multipart/form-data']],
            output: FlowEventDeployment::class,
            status: 201,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(
                tags: ['Flowable/Events'],
                requestBody: new RequestBody(
                    content: new \ArrayObject([
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['file'],
                                'properties' => [
                                    'file' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                        'description' => 'Event registry resource file (.event or .channel) — no .bar/.zip bundles here',
                                    ],
                                    'deployment-name' => [
                                        'type' => 'string',
                                        'description' => 'Deployment name (defaults to the file name)',
                                    ],
                                    'category' => [
                                        'type' => 'string',
                                        'description' => 'Optional deployment category',
                                    ],
                                    'tenantId' => [
                                        'type' => 'string',
                                        'description' => 'Optional tenant id',
                                    ],
                                    'apiConfiguration' => [
                                        'type' => 'string',
                                        'description' => 'Flowable ApiConfiguration UUID (full or partial)',
                                    ],
                                ],
                            ],
                        ],
                    ]),
                ),
            ),
        ),
        new Delete(
            uriTemplate: '/event_deployments/{id}',
            name: 'flow_event_deployment_delete',
            description: 'Delete an event deployment (removes its event and channel definitions)',
            processor: EventDeploymentDeleteProcessor::class,
            // No Doctrine entity to load — skip the read step (which would 404 via
            // the default provider) and let the processor delete on the engine.
            read: false,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_event_deployment:read']],
    openapi: new Operation(tags: ['Flowable/Events']),
)]
final class FlowEventDeployment
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_event_deployment:read'])]
    public ?string $id = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $name = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $deploymentTime = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $category = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $url = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $parentDeploymentId = null;

    #[Groups(['flow_event_deployment:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_event_deployment:raw group. */
    #[Groups(['flow_event_deployment:raw'])]
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
        $self->name = $data['name'] ?? null;
        $self->deploymentTime = $data['deploymentTime'] ?? null;
        $self->category = $data['category'] ?? null;
        $self->url = $data['url'] ?? null;
        $self->parentDeploymentId = $data['parentDeploymentId'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
