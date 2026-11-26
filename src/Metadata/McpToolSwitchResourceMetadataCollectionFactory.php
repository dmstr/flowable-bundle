<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Applies the dmstr_flowable.mcp.read / mcp.write / mcp.deploy switches to
 * the bundle's own McpTool and McpResource entries.
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
 *    mcp.deploy;
 *  - an McpResource is read-only by nature and kept only when mcp.read is
 *    on. The bundle declares none today; the rule keeps a future one
 *    closed until mcp.read is switched on.
 *
 * Independently of the switches, a tool whose input schema cannot be built
 * ({@see McpToolInputSchemaFactory::inputSchema()} throws: missing file,
 * invalid JSON, `..` in the schema path) is removed as well and the error is
 * logged; all other tools stay available. This runs when API Platform builds
 * its metadata (cache warmup), so the log entry appears once per warmup.
 *
 * Only resources under Dmstr\Flowable\ApiResource\ are touched
 * ({@see self::isBundleResource()}); other bundles' and the application's
 * resources pass through unchanged, including their McpTool and McpResource
 * entries, and so do all HTTP operations.
 *
 * This factory sits inside API Platform's metadata cache (see
 * config/services.yaml), so the filtered metadata is what gets cached: the
 * switches take effect at cache warmup, a changed switch needs cache:clear,
 * and DependencyInjection\LiteralMcpSwitchesPass therefore accepts only
 * literal booleans.
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
        private readonly LoggerInterface $flowableLogger = new NullLogger(),
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        // No early return when all switches are on: a tool with a broken
        // input schema is removed in every configuration.
        if (!self::isBundleResource($resourceClass)) {
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
     * Whether a class is one of this bundle's API resources, the only ones
     * the tool switches and the MCP input schema factory act on.
     */
    public static function isBundleResource(string $class): bool
    {
        return str_starts_with(ltrim($class, '\\'), self::RESOURCE_NAMESPACE);
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

        // McpTool and McpResource entries live in the resource's mcp list;
        // strip any that ended up among the HTTP operations as well, so a
        // disabled entry is no operation at all.
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
        if ($operation instanceof McpResource) {
            return $this->readEnabled;
        }
        if (!$operation instanceof McpTool) {
            return true;
        }

        return $this->switchedOn($operation) && $this->hasBuildableInputSchema($operation);
    }

    private function switchedOn(McpTool $tool): bool
    {
        if (self::isReadOnly($tool)) {
            return $this->readEnabled;
        }
        if (($tool->getExtraProperties()[self::SWITCH_KEY] ?? null) === 'deploy') {
            return $this->writeEnabled && $this->deployEnabled;
        }

        return $this->writeEnabled;
    }

    /**
     * A tool without an input file keeps API Platform's derived schema and
     * counts as buildable; a declared file that cannot be loaded hides the
     * tool instead of breaking tools/list or letting calls through
     * unvalidated.
     */
    private function hasBuildableInputSchema(McpTool $tool): bool
    {
        try {
            McpToolInputSchemaFactory::inputSchema($tool);

            return true;
        } catch (\Throwable $e) {
            $this->flowableLogger->error('flowable.mcp.tool_hidden', [
                'tool' => $tool->getName(),
                'reason' => 'input schema cannot be built',
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return false;
        }
    }
}
// - revised 2026-10-09 (mcp.deploy switch)
// - revised 2026-11-26 (hide tools whose input schema cannot be built)
// - revised 2026-11-26 (McpResource entries follow mcp.read)
