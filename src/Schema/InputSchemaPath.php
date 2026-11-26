<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:18:57 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

/**
 * Builds the absolute path of an operation input schema file
 * (`src/ApiResource/<Resource>/<verb>.input.json`), the one place that knows
 * where these files live. It only builds the path; whether the file exists
 * or is valid is checked by the caller (e.g. InputSchemaLoader).
 */
final class InputSchemaPath
{
    /** The input file of an operation, e.g. ('FlowTask', 'complete'). */
    public static function forOperation(string $resource, string $verb): string
    {
        return self::root().$resource.'/'.$verb.'.input.json';
    }

    /**
     * An input file given relative to src/ApiResource, e.g.
     * 'FlowTask/complete.input.json'; leading slashes are ignored.
     */
    public static function relative(string $path): string
    {
        return self::root().ltrim($path, '/');
    }

    /** The directory of the input files, with a trailing slash. */
    private static function root(): string
    {
        return \dirname(__DIR__).'/ApiResource/';
    }
}
