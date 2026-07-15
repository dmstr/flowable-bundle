<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\Controller\ProcessDefinitionStartInputSchemaController;
use Dmstr\Flowable\State\FlowProcessDefinitionProvider;
use Dmstr\Flowable\State\ProcessDefinitionStartProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable process definitions.
 *
 * The collection lists ALL versions by default (no implicit latest=true);
 * pass ?latest=true to keep only the newest version per key (design D2).
 * Read is open to ROLE_USER; starting an instance requires ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowProcessDefinition',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Process Definition'],
    operations: [
        new GetCollection(
            uriTemplate: '/process_definitions',
            provider: FlowProcessDefinitionProvider::class,
            parameters: [
                'latest' => new QueryParameter(description: 'Keep only the latest version per key'),
                'key' => new QueryParameter(description: 'Filter by process definition key'),
                'category' => new QueryParameter(description: 'Filter by category'),
                'deployment' => new QueryParameter(description: 'Filter by deployment (IRI or id) — relation filter'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/process_definitions/{id}',
            provider: FlowProcessDefinitionProvider::class,
        ),
        new Post(
            uriTemplate: '/process_definitions/{id}/start',
            name: 'flow_process_definition_start',
            description: 'Start a new process instance from this definition',
            processor: ProcessDefinitionStartProcessor::class,
            deserialize: false,
            validate: false,
            output: FlowProcessInstance::class,
            status: 201,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            // Points the client at the per-definition start-form schema
            // (resolved at runtime); the static requestBody stays the generic
            // businessKey/variables/apiConfiguration body.
            openapi: new Operation(
                tags: ['Flowable'],
                extensionProperties: ['x-input-schema-url' => '/api/flowable/process_definitions/{id}/input_schema'],
            ),
        ),
        new Get(
            uriTemplate: '/process_definitions/{id}/input_schema',
            name: 'flow_process_definition_input_schema',
            description: 'Per-definition JSON-Schema for starting a process instance (Jedison form).',
            controller: ProcessDefinitionStartInputSchemaController::class,
            read: false,
            output: false,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                tags: ['Flowable'],
                summary: 'Per-definition input JSON-Schema for the start operation.',
            ),
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_process_definition:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowProcessDefinition
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_process_definition:read'])]
    public ?string $id = null;

    #[Groups(['flow_process_definition:read'])]
    public ?string $key = null;

    #[Groups(['flow_process_definition:read'])]
    public ?string $name = null;

    #[Groups(['flow_process_definition:read'])]
    public ?int $version = null;

    #[Groups(['flow_process_definition:read'])]
    public ?string $description = null;

    #[Groups(['flow_process_definition:read'])]
    public ?string $category = null;

    #[Groups(['flow_process_definition:read'])]
    public ?string $deploymentId = null;

    /** Navigable IRI link to the deployment this definition belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_process_definition:read'])]
    public ?FlowDeployment $deployment = null;

    #[Groups(['flow_process_definition:read'])]
    public bool $suspended = false;

    #[Groups(['flow_process_definition:read'])]
    public bool $startFormDefined = false;

    #[Groups(['flow_process_definition:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_process_definition:raw group. */
    #[Groups(['flow_process_definition:raw'])]
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
        $self->deploymentId = $data['deploymentId'] ?? null;
        if ($self->deploymentId !== null) {
            $self->deployment = FlowDeployment::reference($self->deploymentId);
        }
        $self->suspended = (bool) ($data['suspended'] ?? false);
        $self->startFormDefined = (bool) ($data['startFormDefined'] ?? false);
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
