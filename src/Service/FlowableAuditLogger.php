<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:27:39 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Psr\Log\LoggerInterface;

/**
 * The one place that knows how an audit entry of a Flowable write is
 * written: message "flowable.<action>", level info, on the dedicated
 * "flowable" logger, with the acting user under "actor".
 *
 * The actor is passed in by the caller instead of being resolved here,
 * because the callers do not share a base class or an identity source: the
 * state processors take it from {@see ActingUserResolver}, other callers
 * (CLI commands, the external worker) have their own notion of who acts.
 *
 * The context is merged as $context + ['actor' => $actor], so a caller that
 * already puts an "actor" key into the context keeps it, and "actor" comes
 * last otherwise.
 *
 * The constructor argument is named $flowableLogger so the bundle's bind to
 * the "flowable" channel applies when the class is autowired.
 */
final class FlowableAuditLogger
{
    private const string MESSAGE_PREFIX = 'flowable.';

    public function __construct(private readonly LoggerInterface $flowableLogger)
    {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function record(string $action, ?string $actor, array $context = []): void
    {
        $this->flowableLogger->info(self::MESSAGE_PREFIX.$action, $context + ['actor' => $actor]);
    }
}
