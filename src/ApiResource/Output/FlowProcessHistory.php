<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource\Output;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Result of the MCP tool flowable_history_get: the history of one process
 * instance (running or ended). Rows are plain arrays with the fields of the
 * matching HTTP resources (FlowHistoricActivity, FlowHistoricVariable,
 * FlowHistoricDecisionExecution), without links and raw payloads. Each list
 * holds at most one page; the *Total fields tell whether it was cut.
 */
final class FlowProcessHistory
{
    #[Groups(['flow_mcp:read'])]
    public string $processInstanceId = '';

    /** @var list<array<string,mixed>> */
    #[Groups(['flow_mcp:read'])]
    public array $activities = [];

    #[Groups(['flow_mcp:read'])]
    public int $activitiesTotal = 0;

    /** @var list<array<string,mixed>> */
    #[Groups(['flow_mcp:read'])]
    public array $variables = [];

    #[Groups(['flow_mcp:read'])]
    public int $variablesTotal = 0;

    /** @var list<array<string,mixed>> */
    #[Groups(['flow_mcp:read'])]
    public array $failedDecisionExecutions = [];

    #[Groups(['flow_mcp:read'])]
    public int $failedDecisionExecutionsTotal = 0;
}
