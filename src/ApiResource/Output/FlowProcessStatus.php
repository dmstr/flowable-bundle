<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource\Output;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Result of the MCP tool flowable_get_process_status: one running process
 * instance, its open user tasks and the BPMN elements it currently waits at.
 * Rows are plain arrays with the fields of the matching HTTP resources
 * (FlowProcessInstance, FlowTask), without links and raw payloads.
 */
final class FlowProcessStatus
{
    /** @var array<string,mixed> */
    #[Groups(['flow_mcp:read'])]
    public array $processInstance = [];

    /** @var list<array<string,mixed>> */
    #[Groups(['flow_mcp:read'])]
    public array $openTasks = [];

    /** Total number of open tasks; openTasks holds at most the first page. */
    #[Groups(['flow_mcp:read'])]
    public int $openTasksTotal = 0;

    /** @var list<string> BPMN element ids of the active executions */
    #[Groups(['flow_mcp:read'])]
    public array $currentActivityIds = [];

    /** @var list<array{id:string,activityId:string}> executions waiting at a BPMN element */
    #[Groups(['flow_mcp:read'])]
    public array $activeExecutions = [];
}
