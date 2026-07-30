<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\Controller\JobStacktraceController;
use Dmstr\Flowable\State\FlowJobProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less, read-only pass-through resource for the engine's own job store
 * — the asynchronous work the engine schedules for itself (async continuations,
 * timers, HTTP tasks with `flowable:async="true"`, …). Without it, a job stuck
 * in backoff or dead-lettered is invisible to the application.
 *
 * **One resource, five engine collections.** Flowable splits jobs across
 * `/management/jobs`, `/timer-jobs`, `/suspended-jobs`, `/deadletter-jobs` and
 * `/history-jobs`, but every one of them answers with the SAME item shape and
 * the same `{data,total,start,size}` envelope — so they are exposed as one
 * resource selected by the `kind` query parameter
 * (`async|timer|suspended|deadletter|history`, default `async`) instead of five
 * near-identical resources. The stores are strictly separate: an id resolves
 * only under its own kind, therefore the item operations honour `kind` too
 * (a deadletter job 404s under `kind=async`).
 *
 * Read-only on purpose: moving, retrying or deleting engine jobs is an operator
 * action with process-wide consequences, not something to expose as a generic
 * pass-through. `GET /jobs/{id}/stacktrace` returns the job's exception
 * stacktrace as plain text and is gated behind ROLE_FLOWABLE_ADMIN, because a
 * Java stacktrace leaks engine and application internals; `history` has no
 * stacktrace endpoint in the engine and always answers 404 here.
 */
#[ApiResource(
    shortName: 'FlowJob',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'Jobs'],
    operations: [
        new GetCollection(
            uriTemplate: '/jobs',
            provider: FlowJobProvider::class,
            parameters: [
                'kind' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                        'enum' => ['async', 'timer', 'suspended', 'deadletter', 'history'],
                        'default' => 'async',
                    ],
                    description: 'Which engine job collection to query (async, timer, suspended, deadletter, history)',
                ),
                'id' => new QueryParameter(description: 'Filter by job id'),
                'executionId' => new QueryParameter(description: 'Filter by execution id'),
                'execution' => new QueryParameter(description: 'Filter by execution (IRI or id) — relation filter'),
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'processInstance' => new QueryParameter(description: 'Filter by process instance (IRI or id) — relation filter'),
                'processDefinitionId' => new QueryParameter(description: 'Filter by process definition id'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'elementId' => new QueryParameter(description: 'Filter by the BPMN element id the job belongs to'),
                'elementName' => new QueryParameter(description: 'Filter by the BPMN element name'),
                'handlerType' => new QueryParameter(description: 'Filter by job handler type (e.g. async-continuation, timer-transition)'),
                'correlationId' => new QueryParameter(description: 'Filter by correlation id'),
                'exceptionMessage' => new QueryParameter(description: 'Filter by exception message'),
                'timersOnly' => new QueryParameter(description: 'Only timer jobs'),
                'messagesOnly' => new QueryParameter(description: 'Only message (async) jobs'),
                'withException' => new QueryParameter(description: 'Only jobs that carry an exception'),
                'withRetriesLeft' => new QueryParameter(description: 'Only jobs that still have retries left'),
                'executable' => new QueryParameter(description: 'Only jobs that are due and executable'),
                'locked' => new QueryParameter(description: 'Only locked jobs'),
                'unlocked' => new QueryParameter(description: 'Only unlocked jobs'),
                'scopeId' => new QueryParameter(description: 'Filter by scope id (CMMN)'),
                'subScopeId' => new QueryParameter(description: 'Filter by sub-scope id (CMMN)'),
                'scopeType' => new QueryParameter(description: 'Filter by scope type (bpmn, cmmn)'),
                'scopeDefinitionId' => new QueryParameter(description: 'Filter by scope definition id'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'tenantIdLike' => new QueryParameter(description: 'Filter by tenant id (LIKE pattern)'),
                'withoutProcessInstanceId' => new QueryParameter(description: 'Only jobs without a process instance'),
                'withoutScopeId' => new QueryParameter(description: 'Only jobs without a scope id'),
                'withoutScopeType' => new QueryParameter(description: 'Only jobs without a scope type'),
                'withoutTenantId' => new QueryParameter(description: 'Only jobs without a tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/jobs/{id}',
            provider: FlowJobProvider::class,
            parameters: [
                'kind' => new QueryParameter(
                    schema: [
                        'type' => 'string',
                        'enum' => ['async', 'timer', 'suspended', 'deadletter', 'history'],
                        'default' => 'async',
                    ],
                    description: 'Which engine job collection the id lives in — ids do not resolve across kinds',
                ),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/jobs/{id}/stacktrace',
            name: 'flow_job_stacktrace',
            description: 'The job\'s exception stacktrace as text/plain (not available for kind=history).',
            controller: JobStacktraceController::class,
            read: false,
            output: false,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
            openapi: new Operation(
                tags: ['Flowable/System'],
                summary: 'Exception stacktrace of a job, as plain text.',
            ),
        ),
    ],
    security: "is_granted('ROLE_USER')",
    paginationEnabled: true,
    paginationItemsPerPage: 30,
    normalizationContext: ['groups' => ['flow_job:read']],
    openapi: new Operation(tags: ['Flowable/System']),
)]
final class FlowJob
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_job:read'])]
    public ?string $id = null;

    /**
     * Which engine collection this row came from — echoed back from the request
     * so a client can round-trip to the item / stacktrace operation.
     */
    #[Groups(['flow_job:read'])]
    public ?string $kind = null;

    #[Groups(['flow_job:read'])]
    public ?string $correlationId = null;

    #[Groups(['flow_job:read'])]
    public ?string $handlerType = null;

    #[Groups(['flow_job:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the process instance this job belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_job:read'])]
    public ?FlowProcessInstance $processInstance = null;

    #[Groups(['flow_job:read'])]
    public ?string $executionId = null;

    /** Navigable IRI link to the execution the job is attached to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_job:read'])]
    public ?FlowExecution $execution = null;

    #[Groups(['flow_job:read'])]
    public ?string $processDefinitionId = null;

    /** Navigable IRI link to the definition (Flowable's denormalised shortcut). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_job:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    #[Groups(['flow_job:read'])]
    public ?string $elementId = null;

    #[Groups(['flow_job:read'])]
    public ?string $elementName = null;

    /** Attempts left; 0 on a dead-lettered job. */
    #[Groups(['flow_job:read'])]
    public ?int $retries = null;

    #[Groups(['flow_job:read'])]
    public ?string $exceptionMessage = null;

    #[Groups(['flow_job:read'])]
    public ?string $lockOwner = null;

    #[Groups(['flow_job:read'])]
    public ?string $lockExpirationTime = null;

    #[Groups(['flow_job:read'])]
    public ?string $dueDate = null;

    #[Groups(['flow_job:read'])]
    public ?string $createTime = null;

    #[Groups(['flow_job:read'])]
    public ?string $scopeId = null;

    #[Groups(['flow_job:read'])]
    public ?string $subScopeId = null;

    #[Groups(['flow_job:read'])]
    public ?string $scopeType = null;

    #[Groups(['flow_job:read'])]
    public ?string $scopeDefinitionId = null;

    #[Groups(['flow_job:read'])]
    public ?string $tenantId = null;

    /** Raw Flowable payload — opt-in via the flow_job:raw group. */
    #[Groups(['flow_job:raw'])]
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
    public static function fromApi(array $data, string $kind = 'async'): self
    {
        $self = new self();
        $self->id = isset($data['id']) ? (string) $data['id'] : null;
        $self->kind = $kind;
        $self->correlationId = $data['correlationId'] ?? null;
        $self->handlerType = $data['handlerType'] ?? null;
        $self->processInstanceId = $data['processInstanceId'] ?? null;
        if ($self->processInstanceId !== null) {
            $self->processInstance = FlowProcessInstance::reference($self->processInstanceId);
        }
        $self->executionId = $data['executionId'] ?? null;
        if ($self->executionId !== null) {
            $self->execution = FlowExecution::reference($self->executionId);
        }
        $self->processDefinitionId = $data['processDefinitionId'] ?? null;
        if ($self->processDefinitionId !== null) {
            $self->processDefinition = FlowProcessDefinition::reference($self->processDefinitionId);
        }
        $self->elementId = $data['elementId'] ?? null;
        $self->elementName = $data['elementName'] ?? null;
        $self->retries = isset($data['retries']) ? (int) $data['retries'] : null;
        $self->exceptionMessage = $data['exceptionMessage'] ?? null;
        $self->lockOwner = $data['lockOwner'] ?? null;
        $self->lockExpirationTime = $data['lockExpirationTime'] ?? null;
        $self->dueDate = $data['dueDate'] ?? null;
        $self->createTime = $data['createTime'] ?? null;
        $self->scopeId = $data['scopeId'] ?? null;
        $self->subScopeId = $data['subScopeId'] ?? null;
        $self->scopeType = $data['scopeType'] ?? null;
        $self->scopeDefinitionId = $data['scopeDefinitionId'] ?? null;
        $self->tenantId = $data['tenantId'] ?? null;
        $self->raw = $data;

        return $self;
    }
}
