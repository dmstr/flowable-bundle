<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Client;

/**
 * Workflow-engine client contract.
 *
 * Deliberately NOT a Dmstr\ApiConfiguration\ApiClient\RestApiClientInterface:
 * that hierarchy's domain methods (getProjects(), getTodos(), ...) are
 * za7-specific and meaningless for a BPMN engine (design D3). All list methods
 * return the raw Flowable envelope ({ data, total, start, size, ... }); item
 * methods return the raw resource array or null when absent.
 *
 * Implementations MUST translate transport and HTTP failures into
 * Dmstr\Flowable\Exception\FlowableApiException (RFC 7807 mapping, design D11).
 */
interface FlowableClientInterface
{
    public function getEndpoint(): string;

    /** @return array{status:string, reachable:bool, info?:array} */
    public function getHealthInfo(): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listDeployments(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findDeployment(string $id): ?array;

    /**
     * Create a deployment by uploading a single resource file (BPMN, DMN, form
     * JSON, or a .bar/.zip bundle). The file extension drives how Flowable
     * interprets the upload, so it must be preserved in $filename.
     *
     * @param array<string,string> $fields extra multipart form fields
     *                                      (deployment-name, deployment-source,
     *                                      tenantId, ...)
     * @return array<string,mixed> the created deployment representation
     */
    public function createDeployment(string $filename, string $content, array $fields = []): array;

    /** Delete a deployment; cascade also removes running/historic instances. */
    public function deleteDeployment(string $id, bool $cascade = false): void;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listProcessDefinitions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findProcessDefinition(string $id): ?array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listProcessInstances(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findProcessInstance(string $id): ?array;

    /**
     * Start a process instance. Payload is the Flowable runtime body
     * (processDefinitionId/Key, startUserId, variables, ...).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed> the created process-instance representation
     */
    public function startProcessInstance(array $payload): array;

    public function deleteProcessInstance(string $id): void;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listTasks(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findTask(string $id): ?array;

    /**
     * Complete a user task. Payload carries action=complete and variables.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null the task state after completion (null if gone)
     */
    public function completeTask(string $id, array $payload): ?array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listExecutions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findExecution(string $id): ?array;

    /**
     * Trigger a waiting execution (e.g. a receive task) via action=trigger.
     * The id MUST be a child/leaf execution that currently references a flow
     * element — never the process-instance execution (Flowable rejects that
     * with "it should not be a process instance execution").
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|null
     */
    public function triggerExecution(string $executionId, array $payload): ?array;

    /**
     * Fetch the process form-data (legacy formProperty engine) for a task, or
     * null when the task has no form. Carries formKey, deploymentId and the
     * resolved formProperties.
     *
     * @return array<string,mixed>|null
     */
    public function getTaskFormData(string $taskId): ?array;

    /**
     * Fetch the runtime variables visible to a task (task-local + process),
     * flattened to a name => value map. Empty when the task is unknown.
     *
     * @return array<string,mixed>
     */
    public function getTaskVariables(string $taskId): array;

    /**
     * Fetch the start-event form-data for a process definition, or null.
     *
     * @return array<string,mixed>|null
     */
    public function getStartFormData(string $processDefinitionId): ?array;

    /**
     * List a deployment's resource descriptors (BPMN, forms, ...).
     *
     * @return list<array<string,mixed>>
     */
    public function listDeploymentResources(string $deploymentId): array;

    /**
     * Fetch a single deployment resource's raw content, or null when absent.
     */
    public function getDeploymentResource(string $deploymentId, string $resourceId): ?string;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoricProcessInstances(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findHistoricProcessInstance(string $id): ?array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoricTasks(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findHistoricTask(string $id): ?array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoricVariables(array $query = []): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoricActivities(array $query = []): array;

    // --- DMN (decision) engine — /dmn-api/* --------------------------------
    // The decision engine ships in the same flowable-rest container and is
    // reached under the /dmn-api prefix (vs. /service for the process engine),
    // resolved from the SAME "flowable" ApiConfiguration. Requires a Flowable
    // engine >= 8.0.0 (the DMN repository resource is named "decisions"; older
    // engines exposed "decision-tables").

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listDmnDeployments(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findDmnDeployment(string $id): ?array;

    /**
     * Create a DMN deployment by uploading a .dmn resource (or a .bar/.zip
     * bundle of them). The file extension drives interpretation, so it must be
     * preserved in $filename.
     *
     * @param array<string,string> $fields extra multipart form fields
     *                                      (deployment-name, tenantId, ...)
     * @return array<string,mixed> the created deployment representation
     */
    public function createDmnDeployment(string $filename, string $content, array $fields = []): array;

    /** Delete a DMN deployment (drops its decision definitions). */
    public function deleteDmnDeployment(string $id): void;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listDecisions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findDecision(string $id): ?array;

    /**
     * Evaluate a decision, returning every matching rule's output row.
     * Payload carries decisionKey and inputVariables (name/type/value list).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed> raw engine result ({ resultVariables: [...] })
     */
    public function executeDecision(array $payload): array;

    /**
     * Evaluate a decision, returning a single output row (the engine enforces
     * a single-result hit policy).
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed> raw engine result ({ resultVariables: [...] })
     */
    public function executeDecisionSingleResult(array $payload): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoricDecisionExecutions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findHistoricDecisionExecution(string $id): ?array;

    // --- External worker jobs — /external-job-api/* -------------------------
    // The external worker (job) API lets an outside worker pull work from
    // bpmn:serviceTask flowable:type="external-worker" activities, do it, and
    // report back. Unlike the process engine it is NOT served under /service
    // but under the sibling prefix /external-job-api, resolved from the SAME
    // "flowable" ApiConfiguration (verified against flowable-rest 8.0.0).
    //
    // Lifecycle: acquire (locks N jobs of one topic for the worker) → work →
    // complete | fail | bpmnError. A worker that cannot do the work reports
    // nothing and unacquires instead, which releases the lock WITHOUT spending
    // one of the job's retries. Backoff and dead-lettering are the engine's
    // business, never the worker's.

    /**
     * Acquire (lock) external worker jobs of a single topic.
     *
     * Payload: topic, workerId, lockDuration (ISO-8601 duration, e.g. PT10M),
     * numberOfTasks (default 1), numberOfRetries (default 5), optional
     * scopeType ("bpmn"/"cmmn"). Unlike every other list endpoint this one
     * answers with a bare JSON array (no {data,total,…} envelope); an empty
     * array means "no work on that topic".
     *
     * @param array<string,mixed> $payload
     * @return list<array<string,mixed>> the acquired jobs, each carrying its variables
     */
    public function acquireExternalWorkerJobs(array $payload): array;

    /**
     * Report an acquired job as done. Payload: workerId (must be the lock
     * owner) and variables as a {name,type,value} list — exactly what
     * FlowableVariableMapper::toFlowable() produces.
     *
     * @param array<string,mixed> $payload
     */
    public function completeExternalWorkerJob(string $id, array $payload): void;

    /**
     * Report an acquired job as failed. Payload: workerId, retries,
     * retryTimeout (ISO-8601 duration), errorMessage, errorDetails. The engine
     * decrements the retries and re-schedules or dead-letters the job.
     *
     * @param array<string,mixed> $payload
     */
    public function failExternalWorkerJob(string $id, array $payload): void;

    /**
     * Raise a BPMN error from an acquired job, so the process can react with an
     * error boundary event. Payload: workerId, errorCode, variables.
     *
     * @param array<string,mixed> $payload
     */
    public function bpmnErrorExternalWorkerJob(string $id, array $payload): void;

    /**
     * Release the lock on a single acquired job without consuming a retry.
     * Payload: workerId.
     *
     * @param array<string,mixed> $payload
     */
    public function unacquireExternalWorkerJob(string $id, array $payload): void;

    /**
     * Release the locks on ALL jobs currently held by a worker — the shutdown
     * path of a worker runner. Payload: workerId and optional tenantId.
     *
     * @param array<string,mixed> $payload
     */
    public function unacquireAllExternalWorkerJobs(array $payload): void;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listExternalWorkerJobs(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findExternalWorkerJob(string $id): ?array;

    // --- Management jobs (read-only) — /service/management/* ----------------
    // The engine's own job store, split into five collections that all share
    // one response shape: async (jobs), timer-jobs, suspended-jobs,
    // deadletter-jobs and history-jobs. Read-only on purpose: moving or
    // deleting engine jobs is an operator action, not an API pass-through.

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listJobs(array $query = []): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listTimerJobs(array $query = []): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listSuspendedJobs(array $query = []): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listDeadLetterJobs(array $query = []): array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listHistoryJobs(array $query = []): array;

    /**
     * Fetch one job. The $kind selects the collection to look in — each
     * collection has its OWN item endpoint and a job is only found under its
     * own kind (verified: a deadletter job 404s under /management/jobs/{id}).
     *
     * @param 'async'|'timer'|'suspended'|'deadletter'|'history' $kind
     * @return array<string,mixed>|null
     */
    public function findJob(string $id, string $kind = 'async'): ?array;

    /**
     * Fetch a job's exception stacktrace as plain text (not JSON), or null when
     * the job is unknown in that collection or carries no exception. Same
     * per-kind rule as findJob(); "history" has NO stacktrace endpoint and
     * always yields null.
     *
     * @param 'async'|'timer'|'suspended'|'deadletter'|'history' $kind
     */
    public function getJobExceptionStacktrace(string $id, string $kind = 'async'): ?string;
}
