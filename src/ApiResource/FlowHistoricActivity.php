<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\FlowHistoricActivityProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable historic activity instances
 * (the steps a process ran through: events, tasks, gateways, ...). Read-only
 * and collection-only — Flowable offers no single-GET; query by process
 * instance (relation filter).
 */
#[ApiResource(
    shortName: 'FlowHistoricActivity',
    routePrefix: '/flowable',
    operations: [
        new GetCollection(
            uriTemplate: '/historic_activities',
            provider: FlowHistoricActivityProvider::class,
            parameters: [
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'historicProcessInstance' => new QueryParameter(description: 'Filter by historic process instance (IRI or id) — relation filter'),
                'activityId' => new QueryParameter(description: 'Filter by BPMN activity id'),
                'activityType' => new QueryParameter(description: 'Filter by activity type (userTask, startEvent, ...)'),
                'finished' => new QueryParameter(description: 'Only finished (true) or unfinished (false) activities'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_historic_activity:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowHistoricActivity
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_historic_activity:read'])]
    public ?string $id = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $activityId = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $activityName = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $activityType = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the historic process instance. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_activity:read'])]
    public ?FlowHistoricProcessInstance $historicProcessInstance = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $processDefinitionId = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $executionId = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $taskId = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $calledProcessInstanceId = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $assignee = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $startTime = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $endTime = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?int $durationInMillis = null;

    #[Groups(['flow_historic_activity:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_historic_activity:raw group. */
    #[Groups(['flow_historic_activity:raw'])]
    public ?array $raw = null;

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->activityId = $data['activityId'] ?? null;
        $self->activityName = $data['activityName'] ?? null;
        $self->activityType = $data['activityType'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->historicProcessInstance = FlowHistoricProcessInstance::reference($self->processInstanceId);
        }
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        $self->executionId = $data['executionId'] ?? null;
        $self->taskId = $data['taskId'] ?? null;
        $self->calledProcessInstanceId = $data['calledProcessInstanceId'] ?? null;
        $self->assignee = $data['assignee'] ?? null;
        $self->startTime = $data['startTime'] ?? null;
        $self->endTime = $data['endTime'] ?? null;
        $self->durationInMillis = isset($data['durationInMillis']) ? (int) $data['durationInMillis'] : null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
