<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

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
use Dmstr\Flowable\State\DmnDeploymentDeleteProcessor;
use Dmstr\Flowable\State\DmnDeploymentUploadProcessor;
use Dmstr\Flowable\State\FlowDmnDeploymentProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable DMN deployments.
 *
 * The DMN (decision) engine keeps its own repository, separate from the process
 * engine's: a .dmn packaged inside a process (.bar) deployment is NOT registered
 * as a decision, so decision tables must be deployed here. The custom collection
 * operation POST /dmn_deployments/upload accepts a multipart .dmn (or a
 * .bar/.zip bundle of them) and forwards it to the engine's dmn-repository.
 * Read is open to ROLE_USER; upload and delete require ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowDmnDeployment',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'DMN Deployments'],
    operations: [
        new GetCollection(
            uriTemplate: '/dmn_deployments',
            provider: FlowDmnDeploymentProvider::class,
            parameters: [
                'name' => new QueryParameter(description: 'Filter by deployment name'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/dmn_deployments/{id}',
            provider: FlowDmnDeploymentProvider::class,
        ),
        new Post(
            uriTemplate: '/dmn_deployments/upload',
            name: 'flow_dmn_deployment_upload',
            description: 'Deploy a .dmn decision resource or a .bar/.zip bundle to the DMN engine',
            processor: DmnDeploymentUploadProcessor::class,
            deserialize: false,
            validate: false,
            inputFormats: ['multipart' => ['multipart/form-data']],
            output: FlowDmnDeployment::class,
            status: 201,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(
                tags: ['Flowable/DMN'],
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
                                        'description' => 'Decision resource file (.dmn) or .bar/.zip bundle',
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
            uriTemplate: '/dmn_deployments/{id}',
            name: 'flow_dmn_deployment_delete',
            description: 'Delete a DMN deployment (removes its decision definitions)',
            processor: DmnDeploymentDeleteProcessor::class,
            // No Doctrine entity to load — skip the read step (which would 404 via
            // the default provider) and let the processor delete on the engine.
            read: false,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_dmn_deployment:read']],
    openapi: new Operation(tags: ['Flowable/DMN']),
)]
final class FlowDmnDeployment
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $id = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $name = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $deploymentTime = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $category = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $url = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $parentDeploymentId = null;

    #[Groups(['flow_dmn_deployment:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_dmn_deployment:raw group. */
    #[Groups(['flow_dmn_deployment:raw'])]
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
