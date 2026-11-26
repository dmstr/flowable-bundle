<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The rules every uploaded resource follows, shared by the single upload
 * (HTTP multipart and MCP inline, see AbstractFlowableProcessor) and the
 * files of flowable_deploy_bundle (DeploymentBundleProcessor).
 *
 * File name: a plain name the engine stores as the resource name and that
 * ends up in the Content-Disposition header of the multipart request to the
 * engine: at most 255 characters, no path ("/", "\"), no "..", no leading
 * ".", no quotes and no control characters (CR/LF among them).
 *
 * Inline content: at most 1 MiB per file after decoding. The schemas' maxLength
 * counts characters of the (possibly base64) string, so it is only a
 * pre-filter; this byte count is the limit. A multipart upload is not inline
 * content: its size is bounded by the web server and PHP's upload limits.
 */
final class UploadRule
{
    public const int MAX_FILE_NAME_LENGTH = 255;

    /** Decoded bytes of one inline file (1 MiB). */
    public const int MAX_INLINE_BYTES = 1024 * 1024;

    /** Files of one flowable_deploy_bundle call. */
    public const int MAX_BUNDLE_FILES = 20;

    /** Decoded bytes of all files of one flowable_deploy_bundle call (4 MiB). */
    public const int MAX_BUNDLE_BYTES = 4 * 1024 * 1024;

    /**
     * Rejects a file name that is not a plain file name.
     *
     * @param string $field how the error names the value, e.g. "name" or "files[0].name"
     * @throws BadRequestHttpException
     */
    public static function assertFileName(string $name, string $field): void
    {
        if ('' === $name
            || mb_strlen($name, 'UTF-8') > self::MAX_FILE_NAME_LENGTH
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, '..')
            || str_starts_with($name, '.')
            || 1 === preg_match('/["\'\x00-\x1F\x7F]/', $name)
        ) {
            throw new BadRequestHttpException(sprintf(
                '%s must be a plain file name: at most %d characters, no path, no "..", not starting with ".", no quotes or control characters.',
                $field,
                self::MAX_FILE_NAME_LENGTH,
            ));
        }
    }

    /**
     * Rejects decoded inline content above {@see self::MAX_INLINE_BYTES}.
     *
     * @param string $field how the error names the value, e.g. "content" or "files[0].content"
     * @throws BadRequestHttpException
     */
    public static function assertInlineSize(string $content, string $field): void
    {
        if (\strlen($content) > self::MAX_INLINE_BYTES) {
            throw new BadRequestHttpException(sprintf(
                '%s exceeds %d bytes (1 MiB) after decoding.',
                $field,
                self::MAX_INLINE_BYTES,
            ));
        }
    }
}
