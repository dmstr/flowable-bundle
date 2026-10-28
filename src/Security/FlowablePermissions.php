<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:40:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Security;

/**
 * The permission attributes the bundle's HTTP operations and MCP tools check
 * with `is_granted('<attribute>')` (subject `null`). The bundle ships no
 * voter and no role mapping: the consuming application grants them, e.g.
 * za7-core collects the `za7_core.permission` tags registered in
 * `config/services.yaml` and offers them for assignment. Without a voter
 * that grants them, every operation and tool is denied.
 *
 * A constants class, never instantiated.
 */
final class FlowablePermissions
{
    /** Read operations of the engine resources, input schemas, `GET /jobs` of any kind */
    public const string READ = 'flowable:read';

    /** `GET /jobs/{id}/stacktrace`: a Java stacktrace leaks engine internals */
    public const string READ_STACKTRACE = 'flowable:read_stacktrace';

    /** The six read MCP tools, the dead-letter job list included */
    public const string MCP_READ = 'flowable:mcp_read';

    /** Upload of BPMN, DMN and event deployments; MCP tools `flowable_deploy_bundle`, `flowable_dmn_deploy` */
    public const string DEPLOY = 'flowable:deploy';

    /** Delete of BPMN, DMN and event deployments */
    public const string DEPLOY_DELETE = 'flowable:deploy_delete';

    /** `POST /decisions/execute`, MCP tool `flowable_dmn_evaluate` */
    public const string DECISION_EVALUATE = 'flowable:decision_evaluate';

    /** Start a process instance (by definition or directly), MCP tool `flowable_start_process` */
    public const string PROCESS_START = 'flowable:process_start';

    /** `DELETE /process_instances/{id}` */
    public const string PROCESS_DELETE = 'flowable:process_delete';

    /** `POST /tasks/{id}/complete`, MCP tool `flowable_complete_task` */
    public const string TASK_COMPLETE = 'flowable:task_complete';

    /** `POST /event_instances`, MCP tool `flowable_events_send` */
    public const string EVENT_SEND = 'flowable:event_send';

    /** `POST /executions/{id}/trigger`, MCP tool `flowable_system_trigger_execution` */
    public const string EXECUTION_TRIGGER = 'flowable:execution_trigger';

    /** The External Worker operations: acquire, complete, fail, bpmnError, unacquire */
    public const string WORKER = 'flowable:worker';

    /** MCP tool `flowable_system_execute_timer_job` */
    public const string JOB_EXECUTE = 'flowable:job_execute';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::READ,
            self::READ_STACKTRACE,
            self::MCP_READ,
            self::DEPLOY,
            self::DEPLOY_DELETE,
            self::DECISION_EVALUATE,
            self::PROCESS_START,
            self::PROCESS_DELETE,
            self::TASK_COMPLETE,
            self::EVENT_SEND,
            self::EXECUTION_TRIGGER,
            self::WORKER,
            self::JOB_EXECUTE,
        ];
    }
}
