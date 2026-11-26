<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:30:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Mcp;

use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;

/**
 * The bundle's MCP tools as declared on the resource classes, read from the
 * real attribute metadata of src/ApiResource without a container, so a test
 * iterates the tools that exist instead of a hand-written list.
 */
final class BundleMcpTools
{
    /**
     * All McpTool operations, by tool name (sorted).
     *
     * @return array<string, McpTool>
     */
    public static function all(): array
    {
        $factory = new AttributesResourceMetadataCollectionFactory();
        $tools = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/ApiResource/*.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\ApiResource\\'.basename($file, '.php');
            foreach ($factory->create($class) as $resource) {
                foreach ($resource->getMcp() ?? [] as $name => $tool) {
                    if ($tool instanceof McpTool) {
                        $tools[(string) ($tool->getName() ?? $name)] = $tool;
                    }
                }
            }
        }
        ksort($tools);

        return $tools;
    }

    /**
     * The write tools: those run by a processor, by tool name (sorted).
     *
     * @return array<string, McpTool>
     */
    public static function writeTools(): array
    {
        return array_filter(self::all(), static fn (McpTool $tool): bool => $tool->getProcessor() !== null);
    }
}
