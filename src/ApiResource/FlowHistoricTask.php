<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\FlowHistoricTaskProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable historic (user) tasks,
 * including completed ones. Read-only.
 */
#[ApiResource(
    shortName: 'FlowHistoricTask',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Historic Task'],
    operations: [
        new GetCollection(
            uriTemplate: '/historic_tasks',
            provider: FlowHistoricTaskProvider::class,
            parameters: [
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'historicProcessInstance' => new QueryParameter(description: 'Filter by historic process instance (IRI or id) — relation filter'),
                'finished' => new QueryParameter(description: 'Only finished (true) or unfinished (false) tasks'),
                'taskDefinitionKey' => new QueryParameter(description: 'Filter by task definition key'),
                'taskAssignee' => new QueryParameter(description: 'Filter by assignee'),
                'processDefinitionKey' => new QueryParameter(description: 'Filter by process definition key'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/historic_tasks/{id}',
            provider: FlowHistoricTaskProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_historic_task:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowHistoricTask
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_historic_task:read'])]
    public ?string $id = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $name = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $description = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $assignee = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $owner = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the historic process instance this task belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_task:read'])]
    public ?FlowHistoricProcessInstance $historicProcessInstance = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $processDefinitionId = null;

    /** Navigable IRI link to the definition (Flowable's denormalised shortcut). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_task:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $executionId = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $taskDefinitionKey = null;

    #[Groups(['flow_historic_task:read'])]
    public ?int $priority = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $startTime = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $endTime = null;

    #[Groups(['flow_historic_task:read'])]
    public ?int $durationInMillis = null;

    #[Groups(['flow_historic_task:read'])]
    public ?int $workTimeInMillis = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $claimTime = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $dueDate = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $deleteReason = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $category = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $formKey = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $parentTaskId = null;

    #[Groups(['flow_historic_task:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_historic_task:raw group. */
    #[Groups(['flow_historic_task:raw'])]
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
        $self->description = $data['description'] ?? null;
        $self->assignee = $data['assignee'] ?? null;
        $self->owner = $data['owner'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->historicProcessInstance = FlowHistoricProcessInstance::reference($self->processInstanceId);
        }
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        if ($self->processDefinitionId !== null) {
            $self->processDefinition = FlowProcessDefinition::reference($self->processDefinitionId);
        }
        $self->executionId = $data['executionId'] ?? null;
        $self->taskDefinitionKey = $data['taskDefinitionKey'] ?? null;
        $self->priority = isset($data['priority']) ? (int) $data['priority'] : null;
        $self->startTime = $data['startTime'] ?? null;
        $self->endTime = $data['endTime'] ?? null;
        $self->durationInMillis = isset($data['durationInMillis']) ? (int) $data['durationInMillis'] : null;
        $self->workTimeInMillis = isset($data['workTimeInMillis']) ? (int) $data['workTimeInMillis'] : null;
        $self->claimTime = $data['claimTime'] ?? null;
        $self->dueDate = $data['dueDate'] ?? null;
        $self->deleteReason = $data['deleteReason'] ?? null;
        $self->category = $data['category'] ?? null;
        $self->formKey = $data['formKey'] ?? null;
        $self->parentTaskId = $data['parentTaskId'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
