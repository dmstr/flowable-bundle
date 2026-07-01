<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\OpenApiJsonSchema\Interface\SchemaProviderInterface;

/**
 * Contributes the "flowable" configuration schema to the unified
 * ApiConfiguration schema (anyOf) maintained by the SchemaRegistry.
 *
 * This is intentionally NOT an ApiExtensionInterface: that contract's
 * createClient() must return a za7-domain ApiClientInterface (getProjects(),
 * getTodos(), ...), which is meaningless for a workflow engine. Flowable
 * clients are resolved by FlowableClientLocator instead, so only the schema
 * needs to be registered here.
 */
final class FlowableSchemaProvider implements SchemaProviderInterface
{
    public function getName(): string
    {
        return 'flowable';
    }

    public function getSchemaPath(): string
    {
        return \dirname(__DIR__, 2).'/schema.json';
    }
}
