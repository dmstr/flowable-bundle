<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\ApiResource\Output;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Result of the MCP tool flowable_get_task_form: the JSON schema of the
 * flowable_complete_task arguments for one user task. The form fields are the
 * properties of schema.properties.variables.
 */
final class FlowTaskForm
{
    #[Groups(['flow_mcp:read'])]
    public string $taskId = '';

    /** @var array<string,mixed> */
    #[Groups(['flow_mcp:read'])]
    public array $schema = [];

    /**
     * @param array<string,mixed> $schema
     */
    public static function of(string $taskId, array $schema): self
    {
        $self = new self();
        $self->taskId = $taskId;
        $self->schema = $schema;

        return $self;
    }
}
