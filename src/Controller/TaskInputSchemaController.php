<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runtime, per-task input schema for completing a user task
 * (GET /flowable/tasks/{id}/input_schema).
 *
 * Resolves through the openapi-json-schema resolver chain with the task id in
 * the context, so the {@see \Dmstr\Flowable\Schema\TaskFormInputSchemaResolver}
 * computes the task-specific form schema. Served as `application/schema+json`
 * for the vue-admin to render before the `complete` call. When the task has no
 * form, an open object schema is returned so completion stays possible.
 */
final class TaskInputSchemaController
{
    public function __construct(
        private readonly InputSchemaResolverInterface $resolver,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $context = ['id' => $id];
        $apiConfiguration = $request->query->get('apiConfiguration');
        if (\is_string($apiConfiguration) && '' !== $apiConfiguration) {
            $context['apiConfiguration'] = $apiConfiguration;
        }

        $schema = $this->resolver->resolve('flow_task_complete', $context) ?? [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'flow_task_complete input',
            'description' => 'This task has no form; complete without variables.',
            'type' => 'object',
            'properties' => new \stdClass(),
        ];

        return new JsonResponse($schema, JsonResponse::HTTP_OK, ['Content-Type' => 'application/schema+json']);
    }
}
