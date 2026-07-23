<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\FlowHistoricDecisionExecutionProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable historic decision executions
 * — the audit of every DMN evaluation (from a process Business Rule Task or a
 * direct POST /decisions/execute). Read-only; the `failed` flag surfaces an
 * evaluation the engine aborted (the full stack trace stays in the engine log).
 */
#[ApiResource(
    shortName: 'FlowHistoricDecisionExecution',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'DMN Executions'],
    operations: [
        new GetCollection(
            uriTemplate: '/historic_decision_executions',
            provider: FlowHistoricDecisionExecutionProvider::class,
            parameters: [
                'decisionKey' => new QueryParameter(description: 'Filter by decision key'),
                'decisionDefinitionId' => new QueryParameter(description: 'Filter by decision definition id'),
                'instanceId' => new QueryParameter(description: 'Filter by process/scope instance id'),
                'executionId' => new QueryParameter(description: 'Filter by execution id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/historic_decision_executions/{id}',
            provider: FlowHistoricDecisionExecutionProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_historic_decision_execution:read']],
    openapi: new Operation(tags: ['Flowable/DMN']),
)]
final class FlowHistoricDecisionExecution
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $id = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $decisionDefinitionId = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $decisionKey = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $decisionName = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $decisionVersion = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $deploymentId = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $instanceId = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $executionId = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $activityId = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $scopeType = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public bool $failed = false;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $startTime = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $endTime = null;

    #[Groups(['flow_historic_decision_execution:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_historic_decision_execution:raw group. */
    #[Groups(['flow_historic_decision_execution:raw'])]
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
        $self->decisionDefinitionId = $data['decisionDefinitionId'] ?? null;
        $self->decisionKey = $data['decisionKey'] ?? null;
        $self->decisionName = $data['decisionName'] ?? null;
        $self->decisionVersion = isset($data['decisionVersion']) ? (string) $data['decisionVersion'] : null;
        $self->deploymentId = $data['deploymentId'] ?? null;
        $self->instanceId = $data['instanceId'] ?? null;
        $self->executionId = $data['executionId'] ?? null;
        $self->activityId = $data['activityId'] ?? null;
        $self->scopeType = $data['scopeType'] ?? null;
        $self->failed = (bool) ($data['failed'] ?? false);
        $self->startTime = $data['startTime'] ?? null;
        $self->endTime = $data['endTime'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
