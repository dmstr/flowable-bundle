<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\Flowable\Service\TaskInputSchema;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runtime, per-task input schema for completing a user task
 * (GET /flowable/tasks/{id}/input_schema).
 *
 * Resolves through the openapi-json-schema resolver chain with the task id in
 * the context (see {@see TaskInputSchema}, shared with the MCP tool
 * flowable_get_task_form), so the
 * {@see \Dmstr\Flowable\Schema\TaskFormInputSchemaResolver} computes the
 * task-specific form schema. Served as `application/schema+json`
 * for the vue-admin to render before the `complete` call. When the task has no
 * form, an open object schema is returned so completion stays possible.
 */
final class TaskInputSchemaController
{
    public function __construct(
        private readonly TaskInputSchema $taskInputSchema,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $apiConfiguration = $request->query->get('apiConfiguration');
        $schema = $this->taskInputSchema->forTask($id, \is_string($apiConfiguration) ? $apiConfiguration : null);

        return new JsonResponse($schema, JsonResponse::HTTP_OK, ['Content-Type' => 'application/schema+json']);
    }
}
// - revised 2026-10-08 (schema resolution shared with the MCP form tool via TaskInputSchema)
