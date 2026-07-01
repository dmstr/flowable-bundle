<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\ActingUserResolver;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared concerns for the synchronous Flowable write processors (design D8):
 * raw-body access, client resolution (explicit apiConfiguration from query or
 * body, else implicit), bundle-local schema paths, and variable mapping.
 */
abstract class AbstractFlowableProcessor
{
    public function __construct(
        protected readonly FlowableClientLocator $locator,
        protected readonly RequestStack $requestStack,
        protected readonly InputSchemaValidator $validator,
        protected readonly FlowableVariableMapper $variableMapper,
        protected readonly ActingUserResolver $actingUser,
        protected readonly LoggerInterface $flowableLogger,
    ) {
    }

    /**
     * @param array<string,mixed> $context
     */
    protected function audit(string $action, array $context = []): void
    {
        $this->flowableLogger->info('flowable.'.$action, $context + ['actor' => $this->actingUser->currentUserId()]);
    }

    protected function rawBody(): string
    {
        return $this->requestStack->getCurrentRequest()?->getContent() ?? '';
    }

    /**
     * @param array<string,mixed> $body decoded request body (may be empty)
     */
    protected function client(array $body = []): FlowableClientInterface
    {
        $id = $this->requestStack->getCurrentRequest()?->query->get('apiConfiguration');
        if (($id === null || $id === '') && isset($body['apiConfiguration'])) {
            $ref = $body['apiConfiguration'];
            $id = \is_array($ref) ? ($ref['uuid'] ?? null) : (\is_string($ref) ? $ref : null);
        }

        return $this->locator->resolve($id !== null && $id !== '' ? (string) $id : null);
    }

    protected function schemaPath(string $entity, string $verb): string
    {
        return \dirname(__DIR__).'/ApiResource/'.$entity.'/'.$verb.'.input.json';
    }

    /**
     * Map request variables and append a non-spoofable actor marker.
     *
     * Flowable 7.2 REST ignores a body startUserId on the runtime start
     * endpoint (it derives the start user from the authenticated REST user),
     * so the za7 actor is recorded as a process variable instead — the
     * fallback anticipated in the design risk notes (verified 2026-06-16).
     *
     * @param array<string,mixed> $body
     * @return list<array{name:string,value:mixed,type:string}>
     */
    protected function variablesWithActor(array $body, string $marker = 'triggeredBy'): array
    {
        $variables = $this->variableMapper->toFlowable($body['variables'] ?? null);
        $actor = $this->actingUser->currentUserId();
        if ($actor !== null) {
            $variables[] = ['name' => $marker, 'value' => $actor, 'type' => 'string'];
        }

        return $variables;
    }
}
