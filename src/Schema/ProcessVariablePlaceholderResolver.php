<?php
// file generated with AI assistance: Claude Code - 2026-07-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

/**
 * Substitutes process-variable placeholders in a (task/start) form schema
 * with the concrete values of a running process instance.
 *
 * Only bare-identifier tokens — `{{ name }}` where `name` matches
 * `[A-Za-z_][A-Za-z0-9_]*` and is a key in the supplied variable map — are
 * replaced. This is what lets a dynamic collection filter such as
 *   "x-collection": "/api/user?rolle=aussendienst_{{ kreisnummer }}"
 * arrive at the client already resolved (…aussendienst_115).
 *
 * Dotted tokens like `{{ jahr.value }}` (Jedison's client-side
 * `x-watch`/`x-template` mechanism, resolved live from sibling form fields)
 * do NOT match and are deliberately left untouched — the two placeholder
 * mechanisms are separated by concern: instance context is resolved here on
 * the server, live user input stays with the client. Unknown identifiers are
 * left as-is as well, so a missing variable never silently corrupts a URL.
 */
final class ProcessVariablePlaceholderResolver
{
    private const TOKEN = '/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/';

    /**
     * @param array<string,mixed> $schema    a JSON-schema fragment (arrays/strings/scalars)
     * @param array<string,mixed> $variables name => value map
     * @return array<string,mixed>
     */
    public static function resolve(array $schema, array $variables): array
    {
        if ($variables === []) {
            return $schema;
        }

        return self::walk($schema, $variables);
    }

    /**
     * @param array<int|string,mixed> $node
     * @param array<string,mixed>     $variables
     * @return array<int|string,mixed>
     */
    private static function walk(array $node, array $variables): array
    {
        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = self::walk($value, $variables);
            } elseif (\is_string($value)) {
                $node[$key] = self::substitute($value, $variables);
            }
        }

        return $node;
    }

    /**
     * @param array<string,mixed> $variables
     */
    private static function substitute(string $value, array $variables): string
    {
        return (string) preg_replace_callback(
            self::TOKEN,
            static function (array $m) use ($variables): string {
                $name = $m[1];
                if (!\array_key_exists($name, $variables)) {
                    return $m[0]; // leave unresolved token untouched
                }

                return self::stringify($variables[$name]);
            },
            $value,
        );
    }

    private static function stringify(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null || \is_scalar($value)) {
            return (string) $value;
        }

        // Non-scalar (array/object) variables cannot be inlined into a string
        // placeholder; drop to empty rather than emit "Array".
        return '';
    }
}
