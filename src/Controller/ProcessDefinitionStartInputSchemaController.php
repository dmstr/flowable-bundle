<?php
// file generated with AI assistance: Claude Code - 2026-07-14 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\Flowable\Security\FlowablePermissions;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
 *
 * The controller checks `flowable:read` itself: API Platform does not
 * evaluate the operation's `security` expression for a custom controller with
 * `read: false` (the expression only filters the hydra:operation list).
 */
final class ProcessDefinitionStartInputSchemaController
{
    public function __construct(
        private readonly InputSchemaResolverInterface $resolver,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted(FlowablePermissions::READ)) {
            throw new AccessDeniedException();
        }

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
// - revised 2026-10-28 (checks flowable:read itself, security of read:false operations is not evaluated)
