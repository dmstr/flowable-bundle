<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;

/**
 * What the permission tests expect, written down once: which permission
 * guards which HTTP operation and which MCP tool, with a request body or
 * arguments the engine stub accepts. The expectations are deliberately not
 * derived from the code under test; {@see \Dmstr\Flowable\Tests\Security\PermissionRegistrationTest}
 * compares them with the metadata.
 */
final class PermissionCatalog
{
    private const string F = ApiTestCase::FLOWABLE;

    /**
     * The 19 sensitive HTTP operations: method, uri, permission, JSON body
     * (null: none) and, for a multipart upload, the file name.
     *
     * @return array<string, array{string, string, string, ?array<string, mixed>, ?string}>
     */
    public static function sensitiveOperations(): array
    {
        return [
            'upload bpmn' => ['POST', self::F . '/deployments/upload', P::DEPLOY, null, 'test.bpmn'],
            'upload dmn' => ['POST', self::F . '/dmn_deployments/upload', P::DEPLOY, null, 'test.dmn'],
            'upload event' => ['POST', self::F . '/event_deployments/upload', P::DEPLOY, null, 'test.event'],
            'delete deployment' => ['DELETE', self::F . '/deployments/d1', P::DEPLOY_DELETE, null, null],
            'delete dmn deployment' => ['DELETE', self::F . '/dmn_deployments/d1', P::DEPLOY_DELETE, null, null],
            'delete event deployment' => ['DELETE', self::F . '/event_deployments/d1', P::DEPLOY_DELETE, null, null],
            'evaluate decision' => ['POST', self::F . '/decisions/execute', P::DECISION_EVALUATE, ['decisionKey' => 'k'], null],
            'start via definition' => ['POST', self::F . '/process_definitions/def-1/start', P::PROCESS_START, [], null],
            'start via instance' => ['POST', self::F . '/process_instances', P::PROCESS_START, ['processDefinitionKey' => 'k'], null],
            'delete process instance' => ['DELETE', self::F . '/process_instances/p1', P::PROCESS_DELETE, null, null],
            'complete task' => ['POST', self::F . '/tasks/t1/complete', P::TASK_COMPLETE, [], null],
            'send event' => ['POST', self::F . '/event_instances', P::EVENT_SEND, [
                'eventDefinitionKey' => 'e',
                'channelDefinitionKey' => 'c',
                'eventPayload' => ['a' => 1],
            ], null],
            'trigger execution' => ['POST', self::F . '/executions/x1/trigger', P::EXECUTION_TRIGGER, [], null],
            'worker acquire' => ['POST', self::F . '/external_worker_jobs/acquire', P::WORKER, ['topic' => 't', 'workerId' => 'w'], null],
            'worker complete' => ['POST', self::F . '/external_worker_jobs/j1/complete', P::WORKER, ['workerId' => 'w'], null],
            'worker fail' => ['POST', self::F . '/external_worker_jobs/j1/fail', P::WORKER, ['workerId' => 'w'], null],
            'worker bpmnError' => ['POST', self::F . '/external_worker_jobs/j1/bpmnError', P::WORKER, ['workerId' => 'w', 'errorCode' => 'x'], null],
            'worker unacquire' => ['POST', self::F . '/external_worker_jobs/j1/unacquire', P::WORKER, ['workerId' => 'w'], null],
            'job stacktrace' => ['GET', self::F . '/jobs/j1/stacktrace', P::READ_STACKTRACE, null, null],
        ];
    }

    /**
     * Read operations: every resource's collection and item, the input-schema
     * operations, and `GET /jobs` of several kinds.
     *
     * @return list<string>
     */
    public static function readUris(): array
    {
        $uris = [];
        foreach ([
            'decisions', 'deployments', 'dmn_deployments', 'event_deployments', 'event_definitions',
            'channel_definitions', 'executions', 'external_worker_jobs', 'historic_process_instances',
            'historic_tasks', 'jobs', 'process_definitions', 'process_instances', 'tasks',
        ] as $collection) {
            $uris[] = self::F . '/' . $collection;
            $uris[] = self::F . '/' . $collection . '/id-1';
        }
        foreach (['historic_activities', 'historic_variables', 'historic_decision_executions'] as $collection) {
            $uris[] = self::F . '/' . $collection;
        }

        return [
            ...$uris,
            self::F . '/tasks/t1/input_schema',
            self::F . '/process_definitions/def-1/input_schema',
            self::F . '/jobs?kind=deadletter',
            self::F . '/jobs?kind=timer',
        ];
    }

    /**
     * The 14 MCP tools: permission and valid arguments for the engine stub.
     *
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function tools(): array
    {
        return [
            'flowable_list_process_definitions' => [P::MCP_READ, []],
            'flowable_list_tasks' => [P::MCP_READ, []],
            'flowable_get_task_form' => [P::MCP_READ, ['id' => 't1']],
            'flowable_get_process_status' => [P::MCP_READ, ['id' => 'p1']],
            'flowable_history_get' => [P::MCP_READ, ['id' => 'p1']],
            'flowable_system_list_deadletter_jobs' => [P::MCP_READ, []],
            'flowable_start_process' => [P::PROCESS_START, ['processDefinitionKey' => 'k']],
            'flowable_complete_task' => [P::TASK_COMPLETE, ['id' => 't1']],
            'flowable_dmn_evaluate' => [P::DECISION_EVALUATE, ['decisionKey' => 'k']],
            'flowable_events_send' => [P::EVENT_SEND, [
                'eventDefinitionKey' => 'e',
                'channelDefinitionKey' => 'c',
                'eventPayload' => ['a' => 1],
            ]],
            'flowable_system_trigger_execution' => [P::EXECUTION_TRIGGER, ['id' => 'x1']],
            'flowable_dmn_deploy' => [P::DEPLOY, ['name' => 'a.dmn', 'content' => '<definitions/>']],
            'flowable_deploy_bundle' => [P::DEPLOY, [
                'deploymentName' => 'bundle',
                'files' => [['name' => 'a.bpmn', 'content' => '<definitions/>']],
            ]],
            'flowable_system_execute_timer_job' => [P::JOB_EXECUTE, ['id' => 'j1']],
        ];
    }

    /**
     * The three operations that run a custom controller with `read: false`
     * (job stacktrace, the two input schemas). API Platform skips its provider
     * chain for them and evaluates their `security` expression nowhere: they
     * answer 200 for every authenticated user, with or without the permission.
     * That predates the permission switch (ROLE_USER could read stacktraces
     * before) and is fixed in the controllers, not in the expressions, so the
     * generic matrices leave them out and
     * {@see UnguardedControllerOperationsTest} states the expected behaviour.
     *
     * @return array<string, array{string, string}> name => [uri, permission]
     */
    public static function controllerOperations(): array
    {
        return [
            'job stacktrace' => [self::F . '/jobs/j1/stacktrace', P::READ_STACKTRACE],
            'task input schema' => [self::F . '/tasks/t1/input_schema', P::READ],
            'definition input schema' => [self::F . '/process_definitions/def-1/input_schema', P::READ],
        ];
    }

    /**
     * @return array<string, array{string, string, string, ?array<string, mixed>, ?string}> sensitive operations the generic matrices can check
     */
    public static function guardedSensitiveOperations(): array
    {
        return array_diff_key(self::sensitiveOperations(), self::controllerOperations());
    }

    /**
     * @return list<string> read uris the generic matrices can check
     */
    public static function guardedReadUris(): array
    {
        $controllerUris = array_column(self::controllerOperations(), 0);

        return array_values(array_diff(self::readUris(), $controllerUris));
    }

    /**
     * @return list<string> sorted names of the tools guarded by $permission
     */
    public static function toolsOf(string $permission): array
    {
        $names = array_keys(array_filter(self::tools(), static fn (array $tool): bool => $tool[0] === $permission));
        sort($names);

        return $names;
    }
}
