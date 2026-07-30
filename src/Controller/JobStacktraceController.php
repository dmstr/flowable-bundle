<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Controller;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\State\FlowJobProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
 */
final class JobStacktraceController
{
    public function __construct(
        private readonly FlowableClientLocator $locator,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
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
