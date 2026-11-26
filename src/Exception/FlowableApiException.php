<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Maps Flowable engine / transport failures to za7 HTTP responses (RFC 7807).
 *
 * Extends HttpException so API Platform renders it as application/problem+json
 * with the carried status code and headers. Mapping (see design D11):
 *   - Flowable 404                  -> 404 Not Found
 *   - Flowable 400 (bad variable)   -> 422 Unprocessable Entity (first line of
 *                                      the engine message, at most
 *                                      {@see self::ENGINE_MESSAGE_MAX_LENGTH} characters)
 *   - Flowable 401/403 (backend)    -> 502 Bad Gateway (credentials are ours, not the caller's)
 *   - timeout / connection refused  -> 504 Gateway Timeout
 *   - any other >= 400              -> 502 Bad Gateway
 *
 * A 502 or 504 carries nothing from the engine (no body, no host, no URL, no
 * transport error text): its message is generic and names a correlation id
 * generated here, which is also sent as the {@see self::CORRELATION_ID_HEADER}
 * response header and is available through {@see self::getCorrelationId()}.
 * The details belong in the `flowable` log, in one entry with the same id,
 * written by the code that has them (FlowableClient).
 */
final class FlowableApiException extends HttpException
{
    public const string CORRELATION_ID_HEADER = 'X-Correlation-Id';

    /** Length limit of the engine message a 422 passes on */
    public const int ENGINE_MESSAGE_MAX_LENGTH = 200;

    public const string NOT_FOUND = 'Flowable resource not found.';
    public const string REJECTED = 'Flowable rejected the request.';
    public const string BACKEND_ERROR = 'Flowable backend error (correlation id: %s).';
    public const string UNREACHABLE = 'Flowable engine unreachable (correlation id: %s).';

    private ?string $correlationId = null;

    /**
     * @param string|null $engineMessage the message of the engine's error
     *        JSON, null when it carries none; used for a 422 only (shortened)
     */
    public static function fromUpstreamStatus(int $upstreamStatus, ?string $engineMessage = null): self
    {
        return match ($upstreamStatus) {
            404 => new self(404, self::NOT_FOUND),
            400 => new self(422, self::rejectedMessage($engineMessage)),
            // 401/403 included: the credentials are ours, not the caller's
            default => self::withCorrelationId(502, self::BACKEND_ERROR),
        };
    }

    public static function unreachable(): self
    {
        return self::withCorrelationId(504, self::UNREACHABLE);
    }

    /**
     * The id that links this error to its entry in the `flowable` log; null
     * for a 404 or 422, which carry no hidden details.
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * The first line of an engine message, trimmed and cut to
     * {@see self::ENGINE_MESSAGE_MAX_LENGTH} characters ("..." appended when
     * cut); null when nothing usable is left.
     */
    public static function shortenEngineMessage(?string $message): ?string
    {
        // CR and LF never occur inside a UTF-8 multibyte sequence, so no /u
        // is needed (and invalid UTF-8 from the engine cannot fail the split)
        $line = trim(preg_split('/\r\n|\n|\r/', (string) $message, 2)[0]);
        if ($line === '') {
            return null;
        }
        if (mb_strlen($line) > self::ENGINE_MESSAGE_MAX_LENGTH) {
            $line = rtrim(mb_substr($line, 0, self::ENGINE_MESSAGE_MAX_LENGTH)).'...';
        }

        return $line;
    }

    private static function rejectedMessage(?string $engineMessage): string
    {
        $short = self::shortenEngineMessage($engineMessage);

        return $short === null ? self::REJECTED : sprintf('Flowable rejected the request: %s', $short);
    }

    private static function withCorrelationId(int $status, string $template): self
    {
        // 16 hex characters: URL and log safe, unguessable enough to grep for
        $id = bin2hex(random_bytes(8));
        $exception = new self($status, sprintf($template, $id), null, [self::CORRELATION_ID_HEADER => $id]);
        $exception->correlationId = $id;

        return $exception;
    }
}
// - revised 2026-11-26 (generic 502/504 messages with a correlation id, shortened 422 message)
