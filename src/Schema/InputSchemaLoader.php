<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:30:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

/**
 * Reads the bundle's operation input schemas (`ApiResource/<Resource>/<verb>.input.json`)
 * and resolves their references to the shared definitions in
 * {@see self::DEFINITIONS}, so every consumer (MCP inputSchema, input
 * validation, OpenAPI requestBody, form-schema fallback) gets a schema
 * without `$ref` and `$defs`.
 *
 * Only references into the shared definition file are supported, written as
 * a path relative to the referencing file plus a `$defs` pointer:
 *
 *     "apiConfiguration": { "$ref": "../../Schema/api-configuration.json#/$defs/apiConfiguration" }
 *
 * Inside the definition file a definition refers to another one as
 * `#/$defs/<name>`. A `$ref` is replaced in place by the keys of the resolved
 * definition, in their order; a key written next to the `$ref` replaces the
 * definition's value of that key (e.g. a more specific `description`). Any
 * other `$ref` is rejected, so no unresolved reference reaches a client.
 */
final class InputSchemaLoader
{
    /** The shared definitions, under `$defs`. */
    public const string DEFINITIONS = __DIR__.'/api-configuration.json';

    private static ?\stdClass $definitions = null;

    /**
     * The resolved schema of a file, decoded with objects kept as \stdClass
     * (so an empty schema object {} stays an object), or null when the file
     * is missing or holds no JSON object.
     */
    public static function load(string $path): ?\stdClass
    {
        $schema = self::decode($path);

        return null === $schema ? null : self::resolve($schema, $path);
    }

    /**
     * {@see self::load()} decoded as associative arrays, as
     * dmstr/openapi-json-schema-bundle's resolvers return schemas.
     *
     * @return array<string,mixed>|null
     */
    public static function loadArray(string $path): ?array
    {
        $schema = self::load($path);

        return null === $schema ? null : self::toArray($schema);
    }

    /**
     * One resolved shared definition as an associative array, for schemas
     * that are built in PHP.
     *
     * @return array<string,mixed>
     */
    public static function definition(string $name): array
    {
        return self::toArray(self::lookup($name));
    }

    private static function decode(string $path): ?\stdClass
    {
        $content = is_file($path) ? file_get_contents($path) : false;
        $schema = false !== $content ? json_decode($content) : null;

        return $schema instanceof \stdClass ? $schema : null;
    }

    private static function resolve(mixed $node, string $file): mixed
    {
        if (\is_array($node)) {
            return array_map(static fn (mixed $value): mixed => self::resolve($value, $file), $node);
        }
        if (!$node instanceof \stdClass) {
            return $node;
        }

        $siblings = get_object_vars($node);
        $resolved = new \stdClass();
        foreach ($siblings as $key => $value) {
            if ('$ref' !== $key) {
                $resolved->{$key} = self::resolve($value, $file);
                continue;
            }
            foreach (get_object_vars(self::reference((string) $value, $file)) as $defKey => $defValue) {
                $resolved->{$defKey} = \array_key_exists($defKey, $siblings) ? self::resolve($siblings[$defKey], $file) : $defValue;
            }
        }

        return $resolved;
    }

    /** The resolved shared definition a `$ref` written in $file points to. */
    private static function reference(string $ref, string $file): \stdClass
    {
        [$target, $pointer] = array_pad(explode('#', $ref, 2), 2, '');
        $targetFile = '' === $target ? $file : \dirname($file).'/'.$target;
        if (realpath($targetFile) !== realpath(self::DEFINITIONS) || 1 !== preg_match('~^/\$defs/([A-Za-z0-9_]+)$~', $pointer, $match)) {
            throw new \RuntimeException(sprintf('Unsupported $ref "%s" in %s: only definitions of %s are resolved.', $ref, $file, self::DEFINITIONS));
        }

        return self::lookup($match[1]);
    }

    private static function lookup(string $name): \stdClass
    {
        self::$definitions ??= self::decode(self::DEFINITIONS)
            ?? throw new \RuntimeException(sprintf('Shared schema definitions not found or invalid: %s', self::DEFINITIONS));
        $definition = self::$definitions->{'$defs'}->{$name} ?? null;
        if (!$definition instanceof \stdClass) {
            throw new \RuntimeException(sprintf('Shared schema definition "%s" not found in %s', $name, self::DEFINITIONS));
        }
        $resolved = self::resolve($definition, self::DEFINITIONS);
        \assert($resolved instanceof \stdClass);

        return $resolved;
    }

    /** @return array<string,mixed> */
    private static function toArray(\stdClass $schema): array
    {
        $array = json_decode(json_encode($schema, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($array));

        return $array;
    }
}
