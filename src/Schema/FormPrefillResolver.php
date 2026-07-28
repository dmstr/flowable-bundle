<?php
// file generated with AI assistance: Claude Code - 2026-07-29 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

/**
 * Prefills form fields with **values** taken from the running process instance.
 *
 * A field of an authored task-form schema names the process variable holding
 * its data with the `x-process-data-var` extension; the resolver moves that
 * variable's value into the field's `default`, so a step that produced data can
 * hand it to the next step's form:
 *
 *   "landkreise": {
 *     "type": "array",
 *     "x-format": "table-object",
 *     "x-process-data-var": "landkreise"
 *   }
 *
 * is served as the same field carrying `"default": [ …rows… ]`.
 *
 * This complements {@see ProcessVariablePlaceholderResolver}, which fills
 * `{{ token }}` placeholders *inside strings* and is therefore limited to
 * scalars by design. Anything structured — the array of rows above — needs a
 * whole-value mechanism, and a dedicated keyword keeps it clearly separated
 * from Jedison's client-side `{{ x.value }}` (`x-watch`/`x-template`), which
 * must stay untouched on the server.
 *
 * The keyword takes a **plain variable name, not a path**. Reaching into
 * another variable's structure (say, the `hydra:member` rows of an HTTP
 * response body) is deliberately left to the process: a service task or
 * execution listener puts them into a variable of their own. Two reasons —
 * data shaping belongs to the process rather than to a form schema, and a form
 * field that reads the same variable its completion writes back becomes a real
 * round trip: reopening an unfinished task then shows the last saved state
 * instead of the original source data.
 *
 * `default` is the target because it needs no client support: a JSON-schema
 * form renderer already treats it as the field's initial value. Values are
 * passed through **as they are** — the bundle does not reshape foreign data;
 * a renderer shows the columns the `items` schema declares and any extra
 * properties (`@id`, `@type`, …) simply travel along.
 */
final class FormPrefillResolver
{
    private const KEYWORD = 'x-process-data-var';

    /**
     * @param array<string,mixed> $schema    a JSON-schema fragment
     * @param array<string,mixed> $variables name => value map of the instance
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
        // Descend first: the prefilled value is data, not schema, and must not
        // be walked afterwards (a fetched row could carry the keyword as a key
        // of its own without meaning anything).
        foreach ($node as $key => $value) {
            if (\is_array($value)) {
                $node[$key] = self::walk($value, $variables);
            }
        }

        if (!isset($node[self::KEYWORD]) || !\is_string($node[self::KEYWORD])) {
            return $node;
        }

        if (!\array_key_exists($node[self::KEYWORD], $variables)) {
            // Leave the keyword in place: an unresolvable source stays visible
            // in the served schema instead of silently becoming an empty field.
            // Renderers ignore unknown `x-` keywords, so this is inert for the
            // client but tells whoever debugs it that the variable was missing.
            return $node;
        }

        $node['default'] = $variables[$node[self::KEYWORD]];
        unset($node[self::KEYWORD]);

        return $node;
    }
}
