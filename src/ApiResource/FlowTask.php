<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\Controller\TaskInputSchemaController;
use Dmstr\Flowable\State\FlowTaskProvider;
use Dmstr\Flowable\State\TaskCompleteProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable (user) tasks.
 *
 * Read is open to ROLE_USER; completing a task requires ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowTask',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Tasks'],
    operations: [
        new GetCollection(
            uriTemplate: '/tasks',
            provider: FlowTaskProvider::class,
            parameters: [
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'processInstance' => new QueryParameter(description: 'Filter by process instance (IRI or id) — relation filter'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'assignee' => new QueryParameter(description: 'Filter by assignee'),
                'taskDefinitionKey' => new QueryParameter(description: 'Filter by task definition key'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/tasks/{id}',
            provider: FlowTaskProvider::class,
        ),
        new Post(
            uriTemplate: '/tasks/{id}/complete',
            name: 'flow_task_complete',
            description: 'Complete this user task, optionally passing variables',
            processor: TaskCompleteProcessor::class,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            // Points the client at the per-task form schema (resolved at
            // runtime); the static requestBody stays the generic variables map.
            openapi: new Operation(
                tags: ['Flowable'],
                extensionProperties: ['x-input-schema-url' => '/api/flowable/tasks/{id}/input_schema'],
            ),
        ),
        new Get(
            uriTemplate: '/tasks/{id}/input_schema',
            name: 'flow_task_input_schema',
            description: 'Per-task JSON-Schema for completing this user task (Jedison form).',
            controller: TaskInputSchemaController::class,
            read: false,
            output: false,
            security: "is_granted('ROLE_USER')",
            openapi: new Operation(
                tags: ['Flowable'],
                summary: 'Per-task input JSON-Schema for the complete operation.',
            ),
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_task:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowTask
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_task:read'])]
    public ?string $id = null;

    #[Groups(['flow_task:read'])]
    public ?string $name = null;

    #[Groups(['flow_task:read'])]
    public ?string $description = null;

    #[Groups(['flow_task:read'])]
    public ?string $assignee = null;

    #[Groups(['flow_task:read'])]
    public ?string $owner = null;

    #[Groups(['flow_task:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the process instance this task belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_task:read'])]
    public ?FlowProcessInstance $processInstance = null;

    #[Groups(['flow_task:read'])]
    public ?string $processDefinitionId = null;

    /** Navigable IRI link to the definition (Flowable's denormalised shortcut). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_task:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    #[Groups(['flow_task:read'])]
    public ?string $executionId = null;

    #[Groups(['flow_task:read'])]
    public ?string $taskDefinitionKey = null;

    #[Groups(['flow_task:read'])]
    public ?int $priority = null;

    #[Groups(['flow_task:read'])]
    public ?string $createTime = null;

    #[Groups(['flow_task:read'])]
    public ?string $dueDate = null;

    #[Groups(['flow_task:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_task:raw group. */
    #[Groups(['flow_task:raw'])]
    public ?array $raw = null;

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->name = $data['name'] ?? null;
        $self->description = $data['description'] ?? null;
        $self->assignee = $data['assignee'] ?? null;
        $self->owner = $data['owner'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->processInstance = FlowProcessInstance::reference($self->processInstanceId);
        }
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        if ($self->processDefinitionId !== null) {
            $self->processDefinition = FlowProcessDefinition::reference($self->processDefinitionId);
        }
        $self->executionId = $data['executionId'] ?? null;
        $self->taskDefinitionKey = $data['taskDefinitionKey'] ?? null;
        $self->priority = isset($data['priority']) ? (int) $data['priority'] : null;
        $self->createTime = $data['createTime'] ?? null;
        $self->dueDate = $data['dueDate'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
