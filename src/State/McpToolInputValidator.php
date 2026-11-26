<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validates the arguments of an MCP tool call against the input schema the
 * tool publishes in tools/list ({@see McpToolInputSchemaFactory::inputSchema()},
 * URI variables included), so what a client is shown and what is enforced is
 * one schema.
 *
 * Used for every tool call of the bundle's resources by the central
 * {@see McpToolInputValidationProvider}, and where no body is validated
 * against the schema file: the read tools (their providers) and
 * flowable_dmn_deploy. Over HTTP (no mcp_data in the context) it does
 * nothing; the HTTP query parameters are read as before.
 *
 * Fail-closed: a call whose operation is not an McpTool, declares no input
 * schema or has one that cannot be built is rejected, never let through
 * unvalidated.
 */
final class McpToolInputValidator
{
    /**
     * @param array<string,mixed> $context operation context (MCP arguments in mcp_data)
     * @throws UnprocessableEntityHttpException when the arguments violate the schema
     * @throws \RuntimeException when the tool has no buildable input schema
     */
    public function validate(Operation $operation, array $context): void
    {
        $arguments = $context['mcp_data'] ?? null;
        if (!\is_array($arguments)) {
            return;
        }

        $payload = json_decode(json_encode(self::jsonObject($arguments), \JSON_THROW_ON_ERROR));
        InputSchemaValidator::assertValid($payload, self::schema($operation));
    }

    /**
     * Tool arguments as a JSON object value. The MCP SDK decodes arguments
     * into PHP arrays, which turns a nested {} into []; empty arrays are
     * therefore read as {} at every level (no input schema has an array that
     * may be empty and is not also allowed as an object).
     *
     * @param array<string,mixed> $arguments
     */
    public static function jsonObject(array $arguments): \stdClass
    {
        return (object) array_map(self::emptyArraysAsObjects(...), $arguments);
    }

    private static function emptyArraysAsObjects(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }
        if ([] === $value) {
            return new \stdClass();
        }

        return array_map(self::emptyArraysAsObjects(...), $value);
    }

    private static function schema(Operation $operation): object
    {
        $name = (string) $operation->getName();
        try {
            $schema = $operation instanceof McpTool ? McpToolInputSchemaFactory::inputSchema($operation) : null;
        } catch (\Throwable $e) {
            throw new \RuntimeException(sprintf('MCP call rejected: the input schema of tool "%s" cannot be built.', $name), 0, $e);
        }
        if (null === $schema) {
            throw new \RuntimeException(sprintf('MCP call rejected: tool "%s" declares no input schema to validate its arguments against.', $name));
        }

        $schema = json_decode(json_encode($schema, \JSON_THROW_ON_ERROR));
        if (!\is_object($schema)) {
            throw new \RuntimeException(sprintf('MCP call rejected: the input schema of tool "%s" is not an object.', $name));
        }

        return $schema;
    }
}
// - revised 2026-11-26 (opis validation shared with InputSchemaValidator)
// - revised 2026-11-26 (docblock: used by the central McpToolInputValidationProvider)
