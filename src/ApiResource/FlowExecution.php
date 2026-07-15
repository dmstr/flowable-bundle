<?php
// file generated with AI assistance: Claude Code - 2026-06-22 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\ExecutionTriggerProcessor;
use Dmstr\Flowable\State\FlowExecutionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable runtime executions — the
 * concurrent branches of a running process instance.
 *
 * A process instance is itself the root execution; while it runs, one or more
 * child/leaf executions reference the flow elements where the process currently
 * waits (a receive task, an intermediate catch event, ...). Such a waiting
 * child execution — never the process-instance execution — is what `trigger`
 * advances. Read is open to ROLE_USER; triggering requires ROLE_FLOWABLE_ADMIN.
 */
#[ApiResource(
    shortName: 'FlowExecution',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Execution'],
    operations: [
        new GetCollection(
            uriTemplate: '/executions',
            provider: FlowExecutionProvider::class,
            parameters: [
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'processInstance' => new QueryParameter(description: 'Filter by process instance (IRI or id) — relation filter'),
                'activityId' => new QueryParameter(description: 'Filter by the BPMN activity id the execution waits at'),
                'parentId' => new QueryParameter(description: 'Filter by parent execution id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/executions/{id}',
            provider: FlowExecutionProvider::class,
        ),
        new Post(
            uriTemplate: '/executions/{id}/trigger',
            name: 'flow_execution_trigger',
            description: 'Trigger this waiting execution (e.g. a receive task), optionally passing variables',
            processor: ExecutionTriggerProcessor::class,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_execution:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowExecution
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_execution:read'])]
    public ?string $id = null;

    #[Groups(['flow_execution:read'])]
    public ?string $parentId = null;

    #[Groups(['flow_execution:read'])]
    public ?string $superExecutionId = null;

    #[Groups(['flow_execution:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the process instance this execution belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_execution:read'])]
    public ?FlowProcessInstance $processInstance = null;

    /** BPMN activity id the execution currently waits at (null on scope/root executions). */
    #[Groups(['flow_execution:read'])]
    public ?string $activityId = null;

    #[Groups(['flow_execution:read'])]
    public bool $suspended = false;

    #[Groups(['flow_execution:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_execution:raw group. */
    #[Groups(['flow_execution:raw'])]
    public ?array $raw = null;

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->parentId = $data['parentId'] ?? null;
        $self->superExecutionId = $data['superExecutionId'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->processInstance = FlowProcessInstance::reference($self->processInstanceId);
        }
        $self->activityId = $data['activityId'] ?? null;
        $self->suspended = (bool) ($data['suspended'] ?? false);
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
