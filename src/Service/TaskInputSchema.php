<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * The per-task input schema for completing a user task, resolved through the
 * openapi-json-schema resolver chain with the task id in the context (so the
 * {@see \Dmstr\Flowable\Schema\TaskFormInputSchemaResolver} computes the
 * task-specific form schema).
 *
 * Shared by the HTTP endpoint (TaskInputSchemaController,
 * GET /flowable/tasks/{id}/input_schema) and the MCP tool
 * flowable_get_task_form (FlowTaskFormProvider).
 */
final class TaskInputSchema
{
    public const OPERATION = 'flow_task_complete';

    public function __construct(
        private readonly InputSchemaResolverInterface $resolver,
    ) {
    }

    /**
     * The resolved schema, or null when the task has no form (or is unknown
     * to the form endpoint).
     *
     * @return array<string,mixed>|null
     */
    public function resolve(string $taskId, ?string $apiConfiguration = null): ?array
    {
        $context = ['id' => $taskId];
        if (null !== $apiConfiguration && '' !== $apiConfiguration) {
            $context['apiConfiguration'] = $apiConfiguration;
        }

        return $this->resolver->resolve(self::OPERATION, $context);
    }

    /**
     * The resolved schema, or an open object schema when the task has no
     * form, so completion without variables stays possible.
     *
     * @return array<string,mixed>
     */
    public function forTask(string $taskId, ?string $apiConfiguration = null): array
    {
        return $this->resolve($taskId, $apiConfiguration) ?? self::withoutForm();
    }

    /** @return array<string,mixed> */
    public static function withoutForm(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => self::OPERATION.' input',
            'description' => 'This task has no form; complete without variables.',
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }
}
