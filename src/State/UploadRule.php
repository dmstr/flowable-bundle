<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The rules every uploaded resource follows, shared by the single upload
 * (HTTP multipart and MCP inline, see AbstractFlowableProcessor), the
 * files of flowable_deploy_bundle (DeploymentBundleProcessor) and the CLI
 * upload commands (AbstractFlowableCommand::uploadFileName()).
 *
 * File name: a plain name the engine stores as the resource name and that
 * ends up in the Content-Disposition header of the multipart request to the
 * engine: at most 255 characters, no path ("/", "\"), no "..", no leading
 * ".", no quotes and no control characters (CR/LF among them).
 *
 * File suffix: the extension must be one the upload accepts, and a name
 * that Flowable reads as a resource of another kind through a double suffix
 * (".dmn.xml", ".cmmn.xml") is refused when that kind's own extension is
 * not accepted either, so "rates.dmn.xml" does not pass as ".xml".
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

    /**
     * Resource kinds Flowable also recognises by the double suffix
     * ".<kind>.xml" (e.g. "rates.dmn.xml" is a DMN resource).
     */
    public const array XML_DOUBLE_SUFFIX_KINDS = ['dmn', 'cmmn'];

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
     * The suffix that excludes $name from an upload accepting
     * $allowedExtensions, compared case-insensitively, or null when the name
     * is accepted: ".<kind>.xml" when the name ends with the double suffix of
     * a kind in {@see self::XML_DOUBLE_SUFFIX_KINDS} whose extension is not
     * allowed, else ".<extension>" when the extension is not allowed ("."
     * for a name without one).
     *
     * @param list<string> $allowedExtensions lower-case, without the dot
     */
    public static function rejectedSuffix(string $name, array $allowedExtensions): ?string
    {
        $lower = strtolower($name);
        foreach (self::XML_DOUBLE_SUFFIX_KINDS as $kind) {
            if (!\in_array($kind, $allowedExtensions, true) && str_ends_with($lower, '.'.$kind.'.xml')) {
                return '.'.$kind.'.xml';
            }
        }
        $extension = strtolower(pathinfo($name, \PATHINFO_EXTENSION));

        return \in_array($extension, $allowedExtensions, true) ? null : '.'.$extension;
    }

    /**
     * Rejects a resource name whose suffix the upload does not accept
     * ({@see self::rejectedSuffix()}); the error names the suffix.
     *
     * @param list<string> $allowedExtensions lower-case, without the dot
     * @param string $hint appended to the list of allowed extensions
     * @throws BadRequestHttpException
     */
    public static function assertResourceSuffix(string $name, array $allowedExtensions, string $hint = ''): void
    {
        $suffix = self::rejectedSuffix($name, $allowedExtensions);
        if (null !== $suffix) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported resource extension "%s". Allowed: %s%s.',
                $suffix,
                implode(', ', $allowedExtensions),
                $hint,
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

    /**
     * Decodes one inline file: $content must be a non-empty string, $encoding
     * is "base64" (strictly decoded) or none (null or ""), and the decoded
     * bytes must pass {@see self::assertInlineSize()}. The caller words the
     * errors, so the single upload and the bundle keep their own messages.
     *
     * @param mixed $content the raw "content" value
     * @param mixed $encoding the raw "contentEncoding" value
     * @param string $field how the size error names the value, e.g. "content" or "files[0].content"
     * @param string $missingMessage error when $content is not a non-empty string
     * @param string $invalidBase64Message error when base64 content does not decode
     * @param string $unsupportedEncodingMessage error for any encoding other than "base64"
     * @return string the decoded content
     * @throws BadRequestHttpException
     */
    public static function decodeInlineContent(
        mixed $content,
        mixed $encoding,
        string $field,
        string $missingMessage,
        string $invalidBase64Message,
        string $unsupportedEncodingMessage,
    ): string {
        if (!\is_string($content) || '' === $content) {
            throw new BadRequestHttpException($missingMessage);
        }
        if ('base64' === $encoding) {
            $content = base64_decode($content, true);
            if (false === $content) {
                throw new BadRequestHttpException($invalidBase64Message);
            }
        } elseif (null !== $encoding && '' !== $encoding) {
            throw new BadRequestHttpException($unsupportedEncodingMessage);
        }
        self::assertInlineSize($content, $field);

        return $content;
    }
}
// - revised 2026-11-26 (inline content decoding shared by single upload and bundle)
// - revised 2026-11-26 (full-suffix rule for resource names, shared with the CLI upload commands)
