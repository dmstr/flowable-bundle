<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use Dmstr\Flowable\State\ExternalWorkerJobAcquireProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobBpmnErrorProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobCompleteProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobFailProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobUnacquireProcessor;
use Dmstr\Flowable\State\FlowExternalWorkerJobProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Doctrine-less pass-through resource for Flowable external worker jobs — the
 * work items a `bpmn:serviceTask flowable:type="external-worker"` hands to a
 * worker outside the engine.
 *
 * The engine serves these under the `/external-job-api` prefix (not `/service`,
 * see FlowableClientInterface). Lifecycle: `acquire` locks a batch of jobs of
 * ONE topic for a worker id, then each job is reported back exactly once with
 * `complete`, `fail` or `bpmnError`. `unacquire` merely releases the lock and —
 * unlike `fail` — does NOT consume one of the job's retries, which makes it the
 * correct answer when the worker cannot do the work at all (no handler, or a
 * shutdown mid-flight).
 *
 * Reading is open to ROLE_USER; every worker operation requires
 * ROLE_FLOWABLE_ADMIN. Note that the actor of these operations is the *worker*,
 * not a za7 user, so no `triggeredBy` variable is injected — the worker id in
 * the request body is the identity that matters to the engine.
 *
 * For a worker written in PHP, prefer the generic runner
 * ({@see \Dmstr\Flowable\Worker\ExternalWorkerRunner}, CLI
 * `flowable:external-worker:run`) over driving these endpoints by hand.
 */
#[ApiResource(
    shortName: 'FlowExternalWorkerJob',
    routePrefix: '/flowable',
    extraProperties: ['label' => 'External worker jobs'],
    operations: [
        new GetCollection(
            uriTemplate: '/external_worker_jobs',
            provider: FlowExternalWorkerJobProvider::class,
            parameters: [
                'id' => new QueryParameter(description: 'Filter by job id'),
                'executionId' => new QueryParameter(description: 'Filter by execution id'),
                'execution' => new QueryParameter(description: 'Filter by execution (IRI or id) — relation filter'),
                'processInstanceId' => new QueryParameter(description: 'Filter by process instance id'),
                'processInstance' => new QueryParameter(description: 'Filter by process instance (IRI or id) — relation filter'),
                'processDefinitionId' => new QueryParameter(description: 'Filter by process definition id'),
                'processDefinition' => new QueryParameter(description: 'Filter by process definition (IRI or id) — relation filter'),
                'elementId' => new QueryParameter(description: 'Filter by the BPMN element id of the external-worker task'),
                'elementName' => new QueryParameter(description: 'Filter by the BPMN element name'),
                'exceptionMessage' => new QueryParameter(description: 'Filter by exception message'),
                'scopeId' => new QueryParameter(description: 'Filter by scope id (CMMN)'),
                'subScopeId' => new QueryParameter(description: 'Filter by sub-scope id (CMMN)'),
                'scopeType' => new QueryParameter(description: 'Filter by scope type (bpmn, cmmn)'),
                'scopeDefinitionId' => new QueryParameter(description: 'Filter by scope definition id'),
                'tenantId' => new QueryParameter(description: 'Filter by tenant id'),
                'tenantIdLike' => new QueryParameter(description: 'Filter by tenant id (LIKE pattern)'),
                'unlocked' => new QueryParameter(description: 'Only jobs that are not locked by a worker'),
                'withException' => new QueryParameter(description: 'Only jobs that carry an exception'),
                'withoutProcessInstanceId' => new QueryParameter(description: 'Only jobs without a process instance'),
                'withoutScopeId' => new QueryParameter(description: 'Only jobs without a scope id'),
                'withoutScopeType' => new QueryParameter(description: 'Only jobs without a scope type'),
                'withoutTenantId' => new QueryParameter(description: 'Only jobs without a tenant id'),
                'apiConfiguration' => new QueryParameter(description: 'Flowable ApiConfiguration UUID (full or partial)'),
            ],
        ),
        new Get(
            uriTemplate: '/external_worker_jobs/{id}',
            provider: FlowExternalWorkerJobProvider::class,
        ),
        new Post(
            uriTemplate: '/external_worker_jobs/acquire',
            name: 'flow_external_worker_job_acquire',
            description: 'Acquire (lock) a batch of external worker jobs of one topic for a worker id',
            processor: ExternalWorkerJobAcquireProcessor::class,
            deserialize: false,
            validate: false,
            output: FlowExternalWorkerJob::class,
            status: 200,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/external_worker_jobs/{id}/complete',
            name: 'flow_external_worker_job_complete',
            description: 'Report this acquired job as done, optionally passing result variables',
            processor: ExternalWorkerJobCompleteProcessor::class,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/external_worker_jobs/{id}/fail',
            name: 'flow_external_worker_job_fail',
            description: 'Report this acquired job as failed; the engine handles backoff and dead-lettering',
            processor: ExternalWorkerJobFailProcessor::class,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/external_worker_jobs/{id}/bpmnError',
            name: 'flow_external_worker_job_bpmnError',
            description: 'Raise a BPMN error from this acquired job so an error boundary event can catch it',
            processor: ExternalWorkerJobBpmnErrorProcessor::class,
            deserialize: false,
            validate: false,
            output: false,
            status: 204,
            security: "is_granted('ROLE_FLOWABLE_ADMIN')",
        ),
        new Post(
            uriTemplate: '/external_worker_jobs/{id}/unacquire',
            name: 'flow_external_worker_job_unacquire',
            description: 'Release the lock on this job without consuming one of its retries',
            processor: ExternalWorkerJobUnacquireProcessor::class,
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
    normalizationContext: ['groups' => ['flow_external_worker_job:read']],
    openapi: new Operation(tags: ['Flowable/System']),
)]
final class FlowExternalWorkerJob
{
    #[ApiProperty(identifier: true)]
    #[Groups(['flow_external_worker_job:read'])]
    public ?string $id = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $correlationId = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $processInstanceId = null;

    /** Navigable IRI link to the process instance this job belongs to. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_external_worker_job:read'])]
    public ?FlowProcessInstance $processInstance = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $executionId = null;

    /** Navigable IRI link to the execution waiting on this job. */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_external_worker_job:read'])]
    public ?FlowExecution $execution = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $processDefinitionId = null;

    /** Navigable IRI link to the definition (Flowable's denormalised shortcut). */
    #[ApiProperty(readableLink: false, writableLink: false)]
    #[Groups(['flow_external_worker_job:read'])]
    public ?FlowProcessDefinition $processDefinition = null;

    /** BPMN element id of the external-worker service task. */
    #[Groups(['flow_external_worker_job:read'])]
    public ?string $elementId = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $elementName = null;

    /** Attempts left before the engine dead-letters the job. */
    #[Groups(['flow_external_worker_job:read'])]
    public ?int $retries = null;

    /** Worker id currently holding the lock (null when the job is free). */
    #[Groups(['flow_external_worker_job:read'])]
    public ?string $lockOwner = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $lockExpirationTime = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $exceptionMessage = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $dueDate = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $createTime = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $scopeId = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $subScopeId = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $scopeType = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $scopeDefinitionId = null;

    #[Groups(['flow_external_worker_job:read'])]
    public ?string $tenantId = null;

    /**
     * Acquired jobs — populated ONLY by POST /external_worker_jobs/acquire, where
     * the resource instance is the acquire *result* (its `id` is the worker id,
     * not a job id) rather than one job. Each entry is the raw engine
     * representation of a locked job including its `variables`. Empty list means
     * the topic had no work; the collection/item operations leave it null.
     *
     * @var list<array<string,mixed>>|null
     */
    #[Groups(['flow_external_worker_job:read'])]
    public ?array $acquired = null;

    /** Raw Flowable payload — opt-in via the flow_external_worker_job:raw group. */
    #[Groups(['flow_external_worker_job:raw'])]
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
        $self->correlationId = $data['correlationId'] ?? null;
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
        $self->lockOwner = $data['lockOwner'] ?? null;
        $self->lockExpirationTime = $data['lockExpirationTime'] ?? null;
        $self->exceptionMessage = $data['exceptionMessage'] ?? null;
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
// - revised 2026-10-08 (processDefinition relation filter)
