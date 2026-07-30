<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Worker;

/**
 * What a handler wants reported back to the engine for one job — the three
 * reports the external job API accepts, as an immutable value object.
 *
 * Constructed only through the named factories, so an outcome cannot carry a
 * combination the engine has no endpoint for (e.g. a bpmnError with a retry
 * timeout).
 *
 * There is deliberately no `unacquire` factory: unacquiring is not a result of
 * doing the work, it is the runner's answer to "nobody can do this right now"
 * (missing handler, shutdown) — see ExternalWorkerRunner.
 */
final readonly class ExternalWorkerOutcome
{
    public const KIND_COMPLETE = 'complete';

    public const KIND_FAIL = 'fail';

    public const KIND_BPMN_ERROR = 'bpmnError';

    /**
     * @param 'complete'|'fail'|'bpmnError' $kind
     * @param array<string,mixed> $variables variables to write, as name => value
     */
    private function __construct(
        public string $kind,
        public array $variables = [],
        public ?string $errorCode = null,
        public ?int $retries = null,
        public ?string $retryTimeout = null,
        public ?string $message = null,
        public ?string $details = null,
    ) {
    }

    /**
     * The work is done. The variables become process variables, so the following
     * BPMN steps can read the result.
     *
     * @param array<string,mixed> $variables
     */
    public static function complete(array $variables = []): self
    {
        return new self(kind: self::KIND_COMPLETE, variables: $variables);
    }

    /**
     * A technical failure. Everything is optional on purpose: with no arguments
     * the ENGINE decides — it decrements the job's retries and re-schedules, or
     * dead-letters once they are used up. Pass $retries/$retryTimeout only to
     * override that policy for this one report.
     */
    public static function fail(
        ?int $retries = null,
        ?string $retryTimeout = null,
        ?string $message = null,
        ?string $details = null,
    ): self {
        return new self(
            kind: self::KIND_FAIL,
            retries: $retries,
            retryTimeout: $retryTimeout,
            message: $message,
            details: $details,
        );
    }

    /**
     * A modelled business failure: the process continues along the error
     * boundary event catching $errorCode. Use this — not fail() — when the
     * outcome is expected and the process knows how to handle it.
     *
     * @param array<string,mixed> $variables
     */
    public static function bpmnError(string $errorCode, array $variables = []): self
    {
        return new self(kind: self::KIND_BPMN_ERROR, variables: $variables, errorCode: $errorCode);
    }
}
