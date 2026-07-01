<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Maps Flowable engine / transport failures to za7 HTTP responses (RFC 7807).
 *
 * Extends HttpException so API Platform renders it as application/problem+json
 * with the carried status code. Mapping (see design D11):
 *   - Flowable 404                  -> 404 Not Found
 *   - Flowable 400 (bad variable)   -> 422 Unprocessable Entity (engine message in detail)
 *   - Flowable 401/403 (backend)    -> 502 Bad Gateway (credentials are ours, not the caller's)
 *   - timeout / connection refused  -> 504 Gateway Timeout
 *   - any other >= 400              -> 502 Bad Gateway
 */
final class FlowableApiException extends HttpException
{
    public static function fromUpstreamStatus(int $upstreamStatus, string $message): self
    {
        $status = match (true) {
            $upstreamStatus === 404 => 404,
            $upstreamStatus === 400 => 422,
            $upstreamStatus === 401, $upstreamStatus === 403 => 502,
            default => 502,
        };

        $detail = match ($status) {
            404 => 'Flowable resource not found.',
            422 => sprintf('Flowable rejected the request: %s', $message),
            default => sprintf('Flowable backend error (upstream %d): %s', $upstreamStatus, $message),
        };

        return new self($status, $detail);
    }

    public static function unreachable(string $reason): self
    {
        return new self(504, sprintf('Flowable engine unreachable: %s', $reason));
    }
}
