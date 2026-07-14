<?php
// file generated with AI assistance: Claude Code - 2026-07-14 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Runtime, per-definition input schema for starting a process instance
 * (GET /flowable/process_definitions/{id}/input_schema) — the start-form
 * counterpart of {@see TaskInputSchemaController}.
 *
 * Resolves through the openapi-json-schema resolver chain with the process
 * definition id in the context, so the
 * {@see \Dmstr\Flowable\Schema\StartFormInputSchemaResolver} computes the
 * definition-specific start form. Served as `application/schema+json` for the
 * vue-admin to render before the `start` call. When the definition has no
 * start form, the chain falls through to the static `start.input.json`
 * (generic businessKey/variables/apiConfiguration body).
 */
final class ProcessDefinitionStartInputSchemaController
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

        $schema = $this->resolver->resolve('flow_process_definition_start', $context) ?? [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => 'flow_process_definition_start input',
            'description' => 'This definition has no start form; start without variables.',
            'type' => 'object',
            'properties' => new \stdClass(),
        ];

        return new JsonResponse($schema, JsonResponse::HTTP_OK, ['Content-Type' => 'application/schema+json']);
    }
}
