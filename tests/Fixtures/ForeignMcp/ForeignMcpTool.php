<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Fixtures\ForeignMcp;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;

/**
 * An MCP tool of the application, outside the bundle's resource namespace,
 * registered only by the `mcp_fixtures` option of
 * {@see \Dmstr\Flowable\Tests\Kernel\TestKernel}. The bundle's central input
 * validation must leave it alone.
 *
 * Its input schema is the one API Platform derives from this class
 * (`count` an integer). It also carries the bundle's extra property naming a
 * bundle input file with `additionalProperties: false`, so a validation that
 * ignored the namespace rule would reject unknown arguments too.
 */
#[ApiResource(
    operations: [],
    mcp: [
        self::TOOL => new McpTool(
            name: self::TOOL,
            description: 'Test-only tool of the application; echoes its arguments.',
            provider: ForeignMcpToolProvider::class,
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowProcessDefinition/mcpList.input.json']],
        ),
    ],
)]
final class ForeignMcpTool
{
    public const string TOOL = 'app_foreign_echo';

    public ?int $count = null;

    public ?string $received = null;
}
