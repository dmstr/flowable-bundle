<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:38:26 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Exception;

use Dmstr\Flowable\Exception\FlowableApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The factories of {@see FlowableApiException}: generic 502/504 messages with
 * a correlation id (also as header), the shortened engine message of a 422,
 * and the unchanged 404.
 */
final class FlowableApiExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(): FlowableApiException, int, string}>
     */
    public static function generic(): iterable
    {
        yield 'engine 500' => [static fn () => FlowableApiException::fromUpstreamStatus(500, 'secret at http://engine.internal'), 502, FlowableApiException::BACKEND_ERROR];
        yield 'engine 401' => [static fn () => FlowableApiException::fromUpstreamStatus(401, 'Unauthorized'), 502, FlowableApiException::BACKEND_ERROR];
        yield 'engine 403' => [static fn () => FlowableApiException::fromUpstreamStatus(403, null), 502, FlowableApiException::BACKEND_ERROR];
        yield 'engine 409' => [static fn () => FlowableApiException::fromUpstreamStatus(409, 'Conflict'), 502, FlowableApiException::BACKEND_ERROR];
        yield 'unreachable' => [static fn () => FlowableApiException::unreachable(), 504, FlowableApiException::UNREACHABLE];
    }

    /**
     * @param \Closure(): FlowableApiException $create
     */
    #[DataProvider('generic')]
    public function testServerSideFailureIsGenericWithACorrelationId(\Closure $create, int $status, string $template): void
    {
        $exception = $create();

        $id = (string) $exception->getCorrelationId();
        self::assertMatchesRegularExpression('/\A[0-9a-f]{16}\z/', $id);
        self::assertSame($status, $exception->getStatusCode());
        self::assertSame(sprintf($template, $id), $exception->getMessage());
        self::assertSame([FlowableApiException::CORRELATION_ID_HEADER => $id], $exception->getHeaders());
        self::assertNull($exception->getPrevious());
    }

    public function testEveryFailureGetsItsOwnId(): void
    {
        self::assertNotSame(
            FlowableApiException::unreachable()->getCorrelationId(),
            FlowableApiException::unreachable()->getCorrelationId(),
        );
    }

    public function testNotFoundIsUnchanged(): void
    {
        $exception = FlowableApiException::fromUpstreamStatus(404, 'Could not find http://engine.internal/x');

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('Flowable resource not found.', $exception->getMessage());
        self::assertNull($exception->getCorrelationId());
        self::assertSame([], $exception->getHeaders());
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function rejected(): iterable
    {
        $max = FlowableApiException::ENGINE_MESSAGE_MAX_LENGTH;

        yield 'one line' => ["Variable 'amount' is not a number.", "Flowable rejected the request: Variable 'amount' is not a number."];
        yield 'first line only' => ["first line\nsecond line\r\nthird", 'Flowable rejected the request: first line'];
        yield 'CRLF' => ["first line\r\nsecond", 'Flowable rejected the request: first line'];
        yield 'trimmed' => ["  padded \t\nnext", 'Flowable rejected the request: padded'];
        yield 'at the limit' => [str_repeat('a', $max), 'Flowable rejected the request: ' . str_repeat('a', $max)];
        yield 'too long' => [str_repeat('a', $max + 1), 'Flowable rejected the request: ' . str_repeat('a', $max) . '...'];
        yield 'multibyte, cut by character' => [str_repeat('ä', $max + 5), 'Flowable rejected the request: ' . str_repeat('ä', $max) . '...'];
        yield 'empty first line' => ["\nsecond", FlowableApiException::REJECTED];
        yield 'blank' => ['   ', FlowableApiException::REJECTED];
        yield 'no message' => [null, FlowableApiException::REJECTED];
    }

    #[DataProvider('rejected')]
    public function testRejectedRequestCarriesTheShortenedFirstLine(?string $engineMessage, string $expected): void
    {
        $exception = FlowableApiException::fromUpstreamStatus(400, $engineMessage);

        self::assertSame(422, $exception->getStatusCode());
        self::assertSame($expected, $exception->getMessage());
        self::assertNull($exception->getCorrelationId());
        self::assertSame([], $exception->getHeaders());
    }
}
