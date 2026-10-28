<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Security\FlowablePermissions;
use Dmstr\Flowable\State\FlowJobProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * A job's exception stacktrace (GET /flowable/jobs/{id}/stacktrace).
 *
 * A controller rather than a state provider, because the engine answers
 * `text/plain` here: a provider would have to return an object for the
 * serializer, and the stacktrace is not a resource — it is a blob. Same reason
 * TaskInputSchemaController exists next to the task provider.
 *
 * The `kind` query parameter selects the job collection (default `async`); ids
 * do not resolve across kinds, so a dead-lettered job needs
 * `?kind=deadletter`. `kind=history` has no stacktrace endpoint in the engine
 * and always yields 404.
 *
 * The controller checks `flowable:read_stacktrace` itself: API Platform does
 * not evaluate the operation's `security` expression for a custom controller
 * with `read: false` (the expression only filters the hydra:operation list).
 */
final class JobStacktraceController
{
    public function __construct(
        private readonly FlowableClientLocator $locator,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        if (!$this->authorizationChecker->isGranted(FlowablePermissions::READ_STACKTRACE)) {
            throw new AccessDeniedException();
        }

        $apiConfiguration = $request->query->get('apiConfiguration');
        $client = $this->locator->resolve(
            \is_string($apiConfiguration) && '' !== $apiConfiguration ? $apiConfiguration : null,
        );

        $kind = FlowJobProvider::normalizeKind((string) ($request->query->get('kind') ?? ''));
        $stacktrace = $client->getJobExceptionStacktrace($id, $kind);

        if ($stacktrace === null || $stacktrace === '') {
            throw new NotFoundHttpException(sprintf(
                'No stacktrace for job "%s" (kind "%s"). The job may not exist in that collection, or carry no exception.',
                $id,
                $kind,
            ));
        }

        return new Response($stacktrace, Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
// - revised 2026-10-28 (checks flowable:read_stacktrace itself, security of read:false operations is not evaluated)
