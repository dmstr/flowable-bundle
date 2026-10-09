<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;

/**
 * Applies the dmstr_flowable.mcp.read / mcp.write / mcp.deploy switches to
 * the bundle's own McpTool operations.
 *
 * API Platform registers every McpTool it finds in the resource metadata as
 * an MCP tool, and resolves a tool call by looking the name up in the same
 * metadata. Removing a tool here therefore removes it completely: it is
 * neither listed nor callable, and no other part of API Platform sees it.
 *
 *  - a tool whose annotations declare readOnlyHint: true is kept only when
 *    mcp.read is on;
 *  - every other tool is treated as writing (fail-closed: a missing or
 *    non-true readOnlyHint never makes a tool read-only) and kept only when
 *    mcp.write is on;
 *  - a tool marked with SWITCH_KEY => 'deploy' (it deploys process or
 *    decision definitions, i.e. code the engine runs) additionally needs
 *    mcp.deploy.
 *
 * Only resources under Dmstr\Flowable\ApiResource\ are touched; other
 * bundles' and the application's resources pass through unchanged, as do
 * McpResource entries and all HTTP operations.
 */
final class McpToolSwitchResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private const RESOURCE_NAMESPACE = 'Dmstr\\Flowable\\ApiResource\\';

    /** Extra property naming the additional switch a tool needs ('deploy'). */
    public const SWITCH_KEY = 'dmstr_flowable_mcp_switch';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly bool $readEnabled = false,
        private readonly bool $writeEnabled = false,
        private readonly bool $deployEnabled = false,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        if (!str_starts_with(ltrim($resourceClass, '\\'), self::RESOURCE_NAMESPACE)
            || ($this->readEnabled && $this->writeEnabled && $this->deployEnabled)) {
            return $collection;
        }

        $resources = [];
        foreach ($collection as $index => $resource) {
            $resources[$index] = $this->filterResource($resource);
        }

        // A fresh collection, so no operation lookup cached on the inner one
        // can still return a removed tool.
        return new ResourceMetadataCollection($resourceClass, $resources);
    }

    /**
     * Whether a tool declares itself read-only. API Platform hands the
     * annotations array to Mcp\Schema\ToolAnnotations::fromArray(), so the
     * documented form is ['readOnlyHint' => true]; a ToolAnnotations-like
     * object with a public readOnlyHint is accepted as well.
     */
    public static function isReadOnly(McpTool $tool): bool
    {
        $annotations = $tool->getAnnotations();
        $hint = match (true) {
            \is_array($annotations) => $annotations['readOnlyHint'] ?? null,
            \is_object($annotations) => $annotations->readOnlyHint ?? null,
            default => null,
        };

        return $hint === true;
    }

    private function filterResource(ApiResource $resource): ApiResource
    {
        $mcp = $resource->getMcp();
        if ($mcp !== null) {
            $resource = $resource->withMcp(array_filter($mcp, $this->keep(...)));
        }

        // McpTool operations live in the resource's mcp list; strip any that
        // ended up among the HTTP operations as well, so a disabled tool is
        // no operation at all.
        $operations = $resource->getOperations();
        if ($operations !== null) {
            $kept = [];
            $changed = false;
            foreach ($operations as $name => $operation) {
                if ($this->keep($operation)) {
                    $kept[$name] = $operation;
                } else {
                    $changed = true;
                }
            }
            if ($changed) {
                $resource = $resource->withOperations(new Operations($kept));
            }
        }

        return $resource;
    }

    private function keep(object $operation): bool
    {
        if (!$operation instanceof McpTool) {
            return true;
        }

        if (self::isReadOnly($operation)) {
            return $this->readEnabled;
        }
        if (($operation->getExtraProperties()[self::SWITCH_KEY] ?? null) === 'deploy') {
            return $this->writeEnabled && $this->deployEnabled;
        }

        return $this->writeEnabled;
    }
}
// - revised 2026-10-09 (mcp.deploy switch)
