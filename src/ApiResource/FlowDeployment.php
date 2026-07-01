<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

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
use Dmstr\Flowable\State\DeploymentDeleteProcessor;
use Dmstr\Flowable\State\DeploymentUploadProcessor;
use Dmstr\Flowable\State\FlowDeploymentProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable deployments.
 *
 * A deployment bundles the resources (BPMN/DMN/form definitions) that back one
 * or more process definitions. The custom collection operation
 * POST /deployments/upload accepts a multipart file upload (a single .bpmn /
 * .dmn / .form / .json resource or a .bar / .zip bundle) and forwards it to the
 * engine's repository service. Read is open to ROLE_USER; upload and delete
 * require ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowDeployment',
    routePrefix: '/flowable',
    operations: [
        new GetCollection(
            uriTemplate: '/deployments',
            provider: FlowDeploymentProvider::class,
            parameters: [
                'name' => new QueryParameter(description: 'Filter by deployment name'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/deployments/{id}',
            provider: FlowDeploymentProvider::class,
        ),
        new Post(
            uriTemplate: '/deployments/upload',
            name: 'flow_deployment_upload',
            description: 'Deploy a BPMN/DMN/form resource or a .bar/.zip bundle to the engine',
            processor: DeploymentUploadProcessor::class,
            deserialize: false,
            validate: false,
            inputFormats: ['multipart' => ['multipart/form-data']],
            output: FlowDeployment::class,
            status: 201,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(
                tags: ['Flowable'],
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
                                        'description' => 'Resource file (.bpmn, .bpmn20.xml, .dmn, .form, .json) or .bar/.zip bundle',
                                    ],
                                    'deployment-name' => [
                                        'type' => 'string',
                                        'description' => 'Deployment name (defaults to the file name)',
                                    ],
                                    'deployment-source' => [
                                        'type' => 'string',
                                        'description' => 'Free-text source marker stored on the deployment',
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
            uriTemplate: '/deployments/{id}',
            name: 'flow_deployment_delete',
            description: 'Delete a deployment (pass ?cascade=true to also drop its instances)',
            processor: DeploymentDeleteProcessor::class,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_deployment:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowDeployment
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_deployment:read'])]
    public ?string $id = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $name = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $deploymentTime = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $category = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $url = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $parentDeploymentId = null;

    /** Navigable IRI link to the parent deployment (call-activity nesting). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_deployment:read'])]
    public ?FlowDeployment $parentDeployment = null;

    #[Groups(['flow_deployment:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_deployment:raw group. */
    #[Groups(['flow_deployment:raw'])]
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
        // Only link when it points at a *different* deployment — Flowable sets
        // parentDeploymentId == id for a plain (non-nested) deployment.
        if ($self->parentDeploymentId !== null && $self->parentDeploymentId !== $self->id) {
            $self->parentDeployment = self::reference($self->parentDeploymentId);
        }
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
