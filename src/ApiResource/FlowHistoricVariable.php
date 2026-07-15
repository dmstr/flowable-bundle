<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\FlowHistoricVariableProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable historic variable instances
 * (the recorded values of process/task variables, i.e. submitted form data).
 * Read-only and collection-only — Flowable offers no single-GET; query by
 * process instance or task (relation filter).
 */
#[ApiResource(
    shortName: 'FlowHistoricVariable',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Variables'],
    operations: [
        new GetCollection(
            uriTemplate: '/historic_variables',
            provider: FlowHistoricVariableProvider::class,
            parameters: [
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'historicProcessInstance' => new QueryParameter(description: 'Filter by historic process instance (IRI or id) — relation filter'),
                'taskId' => new QueryParameter(description: 'Filter by task id'),
                'historicTask' => new QueryParameter(description: 'Filter by historic task (IRI or id) — relation filter'),
                'variableName' => new QueryParameter(description: 'Filter by variable name'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_historic_variable:read']],
    openapi: new Operation(tags: ['Flowable/History']),
)]
final class FlowHistoricVariable
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_historic_variable:read'])]
    public ?string $id = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $name = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $type = null;

    #[Groups(['flow_historic_variable:read'])]
    public mixed $value = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $scope = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the historic process instance. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_variable:read'])]
    public ?FlowHistoricProcessInstance $historicProcessInstance = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $taskId = null;

    /** Navigable IRI link to the historic task (when the variable is task-scoped). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_variable:read'])]
    public ?FlowHistoricTask $historicTask = null;

    #[Groups(['flow_historic_variable:read'])]
    public ?string $executionId = null;

    /** Raw Flowable payload — opt-in via the flow_historic_variable:raw group. */
    #[Groups(['flow_historic_variable:raw'])]
    public ?array $raw = null;

    /**
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data): self
    {
        $variable = \is_array($data['variable'] ?? null) ? $data['variable'] : [];

        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->name = $variable['name'] ?? null;
        $self->type = $variable['type'] ?? null;
        $self->value = $variable['value'] ?? null;
        $self->scope = $variable['scope'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->historicProcessInstance = FlowHistoricProcessInstance::reference($self->processInstanceId);
        }
        $self->taskId = $data['taskId'] ?? null;
        if ($self->taskId !== null) {
            $self->historicTask = FlowHistoricTask::reference($self->taskId);
        }
        $self->executionId = $data['executionId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
