<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Dynamic, per-task input schema for the `flow_task_complete` operation.
 *
 * Plugs into the openapi-json-schema resolver chain. It only claims the
 * operation when a task id is present in the resolution $context — i.e. at
 * runtime (the per-task form endpoint), NOT at OpenAPI build time, where the
 * static `FlowTask/complete.input.json` (served by the file resolver) remains
 * the documented request body.
 *
 * The resolved field schema (from a {@see TaskFormSchemaSourceInterface}) is
 * wrapped in the same envelope as `complete.input.json` — under `variables`,
 * plus `apiConfiguration` — so {@see \Dmstr\Flowable\State\TaskCompleteProcessor}
 * consumes it unchanged.
 */
final class TaskFormInputSchemaResolver implements InputSchemaResolverInterface
{
    private const OPERATION = 'flow_task_complete';

    /** @var list<TaskFormSchemaSourceInterface> */
    private readonly array $sources;

    /**
     * @param iterable<TaskFormSchemaSourceInterface> $sources priority-ordered
     */
    public function __construct(
        private readonly FlowableClientLocator $locator,
        iterable $sources,
    ) {
        $this->sources = $sources instanceof \Traversable
            ? iterator_to_array($sources, false)
            : array_values($sources);
    }

    public function supports(string $operationName, array $context = []): bool
    {
        return self::OPERATION === $operationName
            && isset($context['id'])
            && '' !== (string) $context['id'];
    }

    public function resolve(string $operationName, array $context = []): ?array
    {
        if (!$this->supports($operationName, $context)) {
            return null;
        }

        $taskId = (string) $context['id'];
        $apiConfiguration = isset($context['apiConfiguration']) ? (string) $context['apiConfiguration'] : null;
        $client = $this->locator->resolve('' !== (string) $apiConfiguration ? $apiConfiguration : null);

        $formData = $client->getTaskFormData($taskId);
        if (null === $formData) {
            return null;
        }

        $fields = null;
        foreach ($this->sources as $source) {
            $fields = $source->build($formData, $client);
            if (null !== $fields) {
                break;
            }
        }
        if (null === $fields) {
            return null;
        }

        // Resolve `{{ processVariable }}` placeholders (e.g. dynamic
        // `x-collection` filters) against the task's runtime variables, so the
        // client receives a self-contained schema. Client-side `{{ x.value }}`
        // (Jedison x-watch/x-template) tokens are left untouched. Only pay for
        // the extra variables call when the schema actually carries a token.
        if (str_contains((string) json_encode($fields), '{{')) {
            $fields = ProcessVariablePlaceholderResolver::resolve($fields, $client->getTaskVariables($taskId));
        }

        return $this->envelope($fields, $client->findTask($taskId));
    }

    /**
     * @param array<string,mixed>      $fields a `type: object` field schema
     * @param array<string,mixed>|null $task   the raw Flowable task (for the title)
     * @return array<string,mixed>
     */
    private function envelope(array $fields, ?array $task): array
    {
        $taskName = \is_array($task) && isset($task['name']) ? (string) $task['name'] : null;
        $hasRequired = isset($fields['required']) && \is_array($fields['required']) && [] !== $fields['required'];

        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => self::OPERATION.' input',
            'description' => null !== $taskName
                ? sprintf('Complete "%s" — provide the task form variables.', $taskName)
                : 'Complete this user task — provide the task form variables.',
            'type' => 'object',
            'properties' => [
                'variables' => $fields,
                'apiConfiguration' => [
                    'description' => 'Optional Flowable ApiConfiguration selector (full or partial UUID).',
                    'oneOf' => [
                        ['type' => 'string', 'pattern' => '^[0-9a-f]{8}(-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})?$'],
                        ['type' => 'object', 'required' => ['uuid'], 'properties' => ['uuid' => ['type' => 'string']], 'additionalProperties' => false],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];
        if ($hasRequired) {
            $schema['required'] = ['variables'];
        }

        return $schema;
    }
}
