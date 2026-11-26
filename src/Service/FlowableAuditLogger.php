<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:27:39 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Dmstr\Flowable\Exception\FlowableApiException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The one place that knows how the audit entries of a Flowable write are
 * written, on the dedicated "flowable" logger.
 *
 * Every write produces two entries, one per state it reaches:
 *
 *  - attempt: message "flowable.<action>.attempt", level info, written
 *    immediately before the engine call (after permission checks and input
 *    validation, so a request rejected by our own code writes nothing);
 *  - success: message "flowable.<action>", level info — the message the
 *    single audit entry had before the states existed, so a consumer that
 *    matches it keeps seeing exactly the successful writes;
 *  - failure: message "flowable.<action>.failure", level warning, written
 *    when the engine call throws; the exception is rethrown unchanged.
 *
 * All three carry the state under "state" as well, so one filter on that key
 * (or on the "flowable.<action>" message prefix) covers them.
 *
 * Context keys, in this order:
 *
 *  1. "state": "attempt" | "success" | "failure" (STATE_* constants);
 *  2. "via": "http" | "mcp" | "cli" | "worker" (VIA_* constants), the
 *     transport the write came in over;
 *  3. "tool": the MCP tool name; present only when "via" is "mcp";
 *  4. "actor": who acts — the authenticated user (http, mcp), the
 *     --acting-user value or, for the external worker job commands, the
 *     worker id (cli), the worker id (worker); null when unknown;
 *  5. the caller's context (ids, file names, hashes, ...); on success the
 *     attempt context followed by the keys derived from the result (a result
 *     key replaces an attempt key of the same name);
 *  6. failure only: "status" (the HTTP status of the exception, null when it
 *     is no HTTP exception), "correlationId" (only when the exception is a
 *     {@see FlowableApiException} with one, i.e. a 502/504: the id of its
 *     flowable.upstream_error entry), "exception" (the exception class).
 *
 * The keys of 1-4 and 6 are reserved: a caller context key of the same name
 * is dropped. (Before the states existed, a caller "actor" won over the
 * passed actor; no caller used that, so it is gone.)
 *
 * The constructor argument is named $flowableLogger so the bundle's bind to
 * the "flowable" channel applies when the class is autowired.
 */
final class FlowableAuditLogger
{
    public const string STATE_ATTEMPT = 'attempt';
    public const string STATE_SUCCESS = 'success';
    public const string STATE_FAILURE = 'failure';

    public const string VIA_HTTP = 'http';
    public const string VIA_MCP = 'mcp';
    public const string VIA_CLI = 'cli';
    public const string VIA_WORKER = 'worker';

    private const string MESSAGE_PREFIX = 'flowable.';

    private const array VIAS = [self::VIA_HTTP, self::VIA_MCP, self::VIA_CLI, self::VIA_WORKER];

    private const array RESERVED_KEYS = [
        'state' => true,
        'via' => true,
        'tool' => true,
        'actor' => true,
        'status' => true,
        'correlationId' => true,
        'exception' => true,
    ];

    public function __construct(private readonly LoggerInterface $flowableLogger)
    {
    }

    /**
     * Run one engine write with its audit states: the attempt entry, then
     * $call; then a success entry with $context plus $successContext($result),
     * or a failure entry with $context and the exception, which is rethrown.
     *
     * @template T
     * @param array<string,mixed> $context what is known before the call
     * @param \Closure(): T $call the engine call
     * @param (\Closure(T): array<string,mixed>)|null $successContext keys derived from the result
     * @return T
     */
    public function audited(
        string $action,
        string $via,
        ?string $actor,
        ?string $tool,
        array $context,
        \Closure $call,
        ?\Closure $successContext = null,
    ): mixed {
        $this->attempt($action, $via, $actor, $tool, $context);
        try {
            $result = $call();
        } catch (\Throwable $e) {
            $this->failure($action, $via, $actor, $tool, $e, $context);

            throw $e;
        }
        $this->success(
            $action,
            $via,
            $actor,
            $tool,
            $successContext === null ? $context : array_merge($context, $successContext($result)),
        );

        return $result;
    }

    /**
     * @param array<string,mixed> $context
     */
    public function attempt(string $action, string $via, ?string $actor, ?string $tool, array $context = []): void
    {
        $this->flowableLogger->info(
            self::MESSAGE_PREFIX.$action.'.'.self::STATE_ATTEMPT,
            $this->entry(self::STATE_ATTEMPT, $via, $actor, $tool, $context),
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    public function success(string $action, string $via, ?string $actor, ?string $tool, array $context = []): void
    {
        $this->flowableLogger->info(
            self::MESSAGE_PREFIX.$action,
            $this->entry(self::STATE_SUCCESS, $via, $actor, $tool, $context),
        );
    }

    /**
     * @param array<string,mixed> $context
     */
    public function failure(string $action, string $via, ?string $actor, ?string $tool, \Throwable $error, array $context = []): void
    {
        $entry = $this->entry(self::STATE_FAILURE, $via, $actor, $tool, $context);
        $entry['status'] = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : null;
        if ($error instanceof FlowableApiException && $error->getCorrelationId() !== null) {
            $entry['correlationId'] = $error->getCorrelationId();
        }
        $entry['exception'] = $error::class;

        $this->flowableLogger->warning(self::MESSAGE_PREFIX.$action.'.'.self::STATE_FAILURE, $entry);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function entry(string $state, string $via, ?string $actor, ?string $tool, array $context): array
    {
        if (!\in_array($via, self::VIAS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown audit origin "%s"; expected one of: %s.', $via, implode(', ', self::VIAS)));
        }
        if ($tool !== null && $via !== self::VIA_MCP) {
            throw new \InvalidArgumentException(sprintf('An audit entry via "%s" carries no MCP tool name.', $via));
        }

        $entry = ['state' => $state, 'via' => $via];
        if ($via === self::VIA_MCP) {
            $entry['tool'] = $tool;
        }
        $entry['actor'] = $actor;

        return $entry + array_diff_key($context, self::RESERVED_KEYS);
    }
}
// - revised 2026-11-26 (attempt/success/failure states, origin, MCP tool name, correlation id)
