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
use Dmstr\Flowable\State\FlowHistoricProcessInstanceProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable historic process instances
 * (completed and running). Read-only; runtime instances leave the runtime once
 * finished, so this is where completed Vorgänge are found.
 */
#[ApiResource(
    shortName: 'FlowHistoricProcessInstance',
    routePrefix: '/flowable',
    operations: [
        new GetCollection(
            uriTemplate: '/historic_process_instances',
            provider: FlowHistoricProcessInstanceProvider::class,
            parameters: [
                'finished' => new QueryParameter(description: 'Only finished (true) or unfinished (false) instances'),
                'processDefinitionKey' => new QueryParameter(description: 'Filter by process definition key'),
                'processDefinitionId' => new QueryParameter(description: 'Filter by process definition id'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'businessKey' => new QueryParameter(description: 'Filter by business key'),
                'startedBy' => new QueryParameter(description: 'Filter by start user id'),
                'finishedAfter' => new QueryParameter(description: 'Finished after the given ISO-8601 time'),
                'finishedBefore' => new QueryParameter(description: 'Finished before the given ISO-8601 time'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/historic_process_instances/{id}',
            provider: FlowHistoricProcessInstanceProvider::class,
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_historic_process_instance:read']],
    openapi: new Operation(tags: ['Flowable']),
)]
final class FlowHistoricProcessInstance
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $id = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $businessKey = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $name = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $processDefinitionId = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $processDefinitionKey = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $processDefinitionName = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?int $processDefinitionVersion = null;

    /** Navigable IRI link to the definition this instance ran. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_historic_process_instance:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $startTime = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $endTime = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?int $durationInMillis = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $startUserId = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $startActivityId = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $endActivityId = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $deleteReason = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $superProcessInstanceId = null;

    #[Groups(['flow_historic_process_instance:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_historic_process_instance:raw group. */
    #[Groups(['flow_historic_process_instance:raw'])]
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
        $self->businessKey = $data['businessKey'] ?? null;
        $self->name = $data['name'] ?? null;
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        if ($self->processDefinitionId !== null) {
            $self->processDefinition = FlowProcessDefinition::reference($self->processDefinitionId);
        }
        $self->processDefinitionKey = $data['processDefinitionKey'] ?? null;
        $self->processDefinitionName = $data['processDefinitionName'] ?? null;
        $self->processDefinitionVersion = isset($data['processDefinitionVersion']) ? (int) $data['processDefinitionVersion'] : null;
        $self->startTime = $data['startTime'] ?? null;
        $self->endTime = $data['endTime'] ?? null;
        $self->durationInMillis = isset($data['durationInMillis']) ? (int) $data['durationInMillis'] : null;
        $self->startUserId = $data['startUserId'] ?? null;
        $self->startActivityId = $data['startActivityId'] ?? null;
        $self->endActivityId = $data['endActivityId'] ?? null;
        $self->deleteReason = $data['deleteReason'] ?? null;
        $self->superProcessInstanceId = $data['superProcessInstanceId'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
