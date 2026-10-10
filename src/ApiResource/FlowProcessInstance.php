<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\ApiResource\Output\FlowProcessStatus;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\FlowProcessInstanceProvider;
use Dmstr\Flowable\State\FlowProcessStatusProvider;
use Dmstr\Flowable\State\ProcessInstanceCreateProcessor;
use Dmstr\Flowable\State\ProcessInstanceDeleteProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable process instances.
 *
 * Read is open to ROLE_USER; create/delete require ROLE_FLOWABLE_ADMIN. The
 * acting za7 user (JWT sub) is propagated as startUserId / the startedBy
 * variable on create — never taken from client input. Advancing a waiting
 * branch is done on FlowExecution (POST /executions/{id}/trigger), because
 * Flowable triggers a child/leaf execution, not the process-instance execution.
 */
#[ApiResource(
    shortName: 'FlowProcessInstance',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Instances'],
    operations: [
        new GetCollection(
            uriTemplate: '/process_instances',
            provider: FlowProcessInstanceProvider::class,
            parameters: [
                'processDefinitionKey' => new QueryParameter(description: 'Filter by process definition key'),
                'processDefinitionId' => new QueryParameter(description: 'Filter by process definition id'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'businessKey' => new QueryParameter(description: 'Filter by business key'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/process_instances/{id}',
            provider: FlowProcessInstanceProvider::class,
        ),
        new Post(
            uriTemplate: '/process_instances',
            name: 'flow_process_instance_create',
            description: 'Start a new process instance by definition key or id',
            processor: ProcessInstanceCreateProcessor::class,
            deserialize: false,
            validate: false,
            output: FlowProcessInstance::class,
            status: 201,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
        new Delete(
            uriTemplate: '/process_instances/{id}',
            name: 'flow_process_instance_delete',
            processor: ProcessInstanceDeleteProcessor::class,
            // No Doctrine entity to load — skip the read step (which would 404 via
            // the default provider) and let the processor delete on the engine.
            read: false,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
    ],
    mcp: [
        'flowable_get_process_status' => new McpTool(
            name: 'flowable_get_process_status',
            description: 'Get the current state of a running process instance in one call. Returns processInstance (id, processDefinitionId, processDefinitionKey, businessKey, startUserId, startTime, suspended, ended, tenantId), openTasks (each with id, name, assignee, taskDefinitionKey, createTime, dueDate), openTasksTotal, currentActivityIds (BPMN element ids it waits at) and activeExecutions (each with id and activityId); ended instances fail, use flowable_history_get for them.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true],
            meta: ['de.dmstr/tag' => 'Flowable'],
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            provider: FlowProcessStatusProvider::class,
            output: FlowProcessStatus::class,
            normalizationContext: ['groups' => ['flow_mcp:read']],
            uriVariables: ['id' => new Link(fromClass: FlowProcessInstance::class, identifiers: ['id'])],
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowProcessInstance/mcpStatus.input.json', 'uriVariables' => ['id' => 'Id of the running process instance.']]],
        ),
        'flowable_start_process' => new McpTool(
            name: 'flowable_start_process',
            description: 'Start a new process instance by processDefinitionKey (latest version, preferred) or processDefinitionId, with optional businessKey and variables, preferably as a map {name: value}. Returns the new process instance with id, processDefinitionId, processDefinitionKey, businessKey, startUserId, startTime, suspended, ended and tenantId.',
            annotations: ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false],
            meta: ['de.dmstr/tag' => 'Flowable'],
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            processor: ProcessInstanceCreateProcessor::class,
            read: false,
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowProcessInstance/create.input.json']],
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_process_instance:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowProcessInstance
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_process_instance:read'])]
    public ?string $id = null;

    #[Groups(['flow_process_instance:read'])]
    public ?string $processDefinitionId = null;

    #[Groups(['flow_process_instance:read'])]
    public ?string $processDefinitionKey = null;

    /** Navigable IRI link to the definition this instance executes. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_process_instance:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    #[Groups(['flow_process_instance:read'])]
    public ?string $businessKey = null;

    #[Groups(['flow_process_instance:read'])]
    public ?string $startUserId = null;

    #[Groups(['flow_process_instance:read'])]
    public ?string $startTime = null;

    #[Groups(['flow_process_instance:read'])]
    public bool $suspended = false;

    #[Groups(['flow_process_instance:read'])]
    public bool $ended = false;

    #[Groups(['flow_process_instance:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_process_instance:raw group. */
    #[Groups(['flow_process_instance:raw'])]
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
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        if ($self->processDefinitionId !== null) {
            $self->processDefinition = FlowProcessDefinition::reference($self->processDefinitionId);
        }
        $self->processDefinitionKey = $data['processDefinitionKey'] ?? null;
        $self->businessKey = $data['businessKey'] ?? null;
        $self->startUserId = $data['startUserId'] ?? null;
        $self->startTime = $data['startTime'] ?? null;
        $self->suspended = (bool) ($data['suspended'] ?? false);
        $self->ended = (bool) ($data['ended'] ?? false);
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
// - revised 2026-10-08 (MCP tools flowable_get_process_status, flowable_start_process)
// - revised 2026-10-09 (MCP: admin-only, destructiveHint)
