<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:23:24 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use Dmstr\Flowable\State\UploadRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * UploadRule::decodeInlineContent(): the one decoding of inline upload
 * content, worded by the caller.
 */
final class UploadRuleTest extends TestCase
{
    private const string MISSING = 'missing';

    private const string INVALID = 'invalid base64';

    private const string UNSUPPORTED = 'unsupported encoding';

    private static function decode(mixed $content, mixed $encoding): string
    {
        return UploadRule::decodeInlineContent($content, $encoding, 'field', self::MISSING, self::INVALID, self::UNSUPPORTED);
    }

    public function testPlainContentIsReturnedUnchanged(): void
    {
        self::assertSame('<xml/>', self::decode('<xml/>', null));
        self::assertSame('<xml/>', self::decode('<xml/>', ''));
    }

    public function testBase64ContentIsDecoded(): void
    {
        self::assertSame('<xml/>', self::decode(base64_encode('<xml/>'), 'base64'));
    }

    /**
     * @return iterable<string, array{mixed, mixed, string}>
     */
    public static function rejected(): iterable
    {
        yield 'missing content' => [null, null, self::MISSING];
        yield 'empty content' => ['', 'base64', self::MISSING];
        yield 'non-string content' => [42, null, self::MISSING];
        yield 'invalid base64' => ['not base64!', 'base64', self::INVALID];
        yield 'unknown encoding' => ['abc', 'gzip', self::UNSUPPORTED];
        yield 'non-string encoding' => ['abc', 1, self::UNSUPPORTED];
    }

    #[DataProvider('rejected')]
    public function testRejectsWithTheCallersMessage(mixed $content, mixed $encoding, string $message): void
    {
        try {
            self::decode($content, $encoding);
            self::fail('Expected a BadRequestHttpException.');
        } catch (BadRequestHttpException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    public function testDecodedContentAbove1MibIsRejectedWithTheField(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('field exceeds 1048576 bytes (1 MiB) after decoding.');
        self::decode(base64_encode(str_repeat('a', UploadRule::MAX_INLINE_BYTES + 1)), 'base64');
    }
}
