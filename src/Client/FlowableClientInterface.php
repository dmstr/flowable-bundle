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

    // --- Event registry engine — /event-registry-api/* ---------------------
    // A THIRD engine repository in the same flowable-rest container, next to
    // the process engine (/service) and the DMN engine (/dmn-api), resolved
    // from the SAME "flowable" ApiConfiguration. It stores event definitions
    // (.event resources) and channel definitions (.channel resources).
    //
    // Why this matters for a pass-through: createEventInstance() hands an event
    // straight to an inbound channel INSIDE the engine, with no message broker
    // in between. Inbound event correlation (BPMN event sub-processes, receive
    // events, boundary events) is therefore usable and verifiable without
    // Kafka/RabbitMQ. The outbound direction (engine → broker) is a separate,
    // broker-dependent concern and is deliberately NOT covered here.
    //
    // Verified against flowable-rest 8.0.0 (2026-07-29).

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listEventDeployments(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findEventDeployment(string $id): ?array;

    /**
     * Create an event-registry deployment by uploading one .event or .channel
     * resource.
     *
     * Two deviations from createDeployment()/createDmnDeployment(), both read
     * out of the engine's own upload handler:
     *  - Only ".event" and ".channel" are accepted. This endpoint has NO
     *    .bar/.zip bundle support (its API description text claims otherwise,
     *    but the code only ever tests those two suffixes), so a multi-resource
     *    deployment has to be uploaded one file at a time.
     *  - The deployment metadata is read from the QUERY STRING, not from
     *    multipart form fields — the handler parses getQueryString() itself —
     *    and the name key is "deploymentName" (camelCase), not the
     *    "deployment-name" used by the other two engines.
     *
     * @param array<string,string> $query deployment metadata, engine spelling
     *                                    (deploymentName, category, tenantId)
     * @return array<string,mixed> the created deployment representation
     */
    public function createEventDeployment(string $filename, string $content, array $query = []): array;

    /**
     * Delete an event-registry deployment, dropping its event and channel
     * definitions. Unlike the process engine there is no cascade option.
     */
    public function deleteEventDeployment(string $id): void;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listEventDefinitions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findEventDefinition(string $id): ?array;

    /** @param array<string,scalar> $query @return array<string,mixed> Flowable list envelope */
    public function listChannelDefinitions(array $query = []): array;

    /** @return array<string,mixed>|null */
    public function findChannelDefinition(string $id): ?array;

    /**
     * Event registry engine info ({ name, version, exception, resourceUrl }) —
     * the event-registry counterpart of the /service/management/engine payload
     * behind getHealthInfo(), useful to confirm the registry is enabled at all.
     *
     * @return array<string,mixed>
     */
    public function getEventRegistryEngineInfo(): array;

    /**
     * Send an event instance into the engine's inbound channel — the
     * broker-less injection path described above.
     *
     * The engine requires BOTH an event definition reference (eventDefinitionId
     * OR eventDefinitionKey) AND a channel definition reference
     * (channelDefinitionId OR channelDefinitionKey); the channel is what turns
     * the raw payload into a correlated event, so it is NOT optional. Optional:
     * tenantId and eventPayload (a JSON object — an array is rejected).
     *
     * Returns nothing: the engine answers 204 No Content on success.
     *
     * @param array<string,mixed> $payload
     */
    public function createEventInstance(array $payload): void;
}
