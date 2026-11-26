<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:01:03 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\CachedResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Pins why a switch change needs cache:clear and why the switches must be
 * literal booleans (see LiteralMcpSwitchesPass): in the container, API
 * Platform's CachedResourceMetadataCollectionFactory (priority -10) wraps the
 * switch decorator (priority 0), so the filtered metadata is what gets
 * cached, and a warm cache never asks the decorator again.
 */
final class McpToolSwitchMetadataCacheTest extends TestCase
{
    private const FLOWABLE_RESOURCE = 'Dmstr\\Flowable\\ApiResource\\FlowTask';

    public function testCacheStoresTheFilteredResultAndIgnoresALaterSwitchChange(): void
    {
        $pool = new ArrayAdapter();

        $readOff = new CachedResourceMetadataCollectionFactory(
            $pool,
            new McpToolSwitchResourceMetadataCollectionFactory($this->inner(), false, true, true),
        );
        self::assertSame(['flow_task_complete'], self::toolNames($readOff->create(self::FLOWABLE_RESOURCE)));

        // The pool holds the filtered result, not the unfiltered one
        $item = $pool->getItem(CachedResourceMetadataCollectionFactory::CACHE_KEY_PREFIX . hash('xxh3', self::FLOWABLE_RESOURCE));
        self::assertTrue($item->isHit());
        self::assertSame(['flow_task_complete'], self::toolNames(new ResourceMetadataCollection(self::FLOWABLE_RESOURCE, $item->get())));

        // A new process (new factory instances, as after a container rebuild
        // without cache:clear) with mcp.read on, behind the same warm cache
        $readOn = new CachedResourceMetadataCollectionFactory(
            $pool,
            new McpToolSwitchResourceMetadataCollectionFactory($this->inner(), true, true, true),
        );
        self::assertSame(['flow_task_complete'], self::toolNames($readOn->create(self::FLOWABLE_RESOURCE)));

        // Only an empty cache (cache:clear) lets the new value take effect
        $pool->clear();
        $cleared = new CachedResourceMetadataCollectionFactory(
            $pool,
            new McpToolSwitchResourceMetadataCollectionFactory($this->inner(), true, true, true),
        );
        self::assertSame(['flow_task_complete', 'flow_task_list'], self::toolNames($cleared->create(self::FLOWABLE_RESOURCE)));
    }

    /**
     * @return list<string> sorted
     */
    private static function toolNames(ResourceMetadataCollection $collection): array
    {
        $names = array_keys(array_filter(
            $collection[0]->getMcp() ?? [],
            static fn (object $op): bool => $op instanceof McpTool,
        ));
        sort($names);

        return $names;
    }

    private function inner(): ResourceMetadataCollectionFactoryInterface
    {
        return new class implements ResourceMetadataCollectionFactoryInterface {
            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [new ApiResource(
                    class: $resourceClass,
                    mcp: [
                        'flow_task_list' => new McpTool(name: 'flow_task_list', annotations: ['readOnlyHint' => true]),
                        'flow_task_complete' => new McpTool(name: 'flow_task_complete', annotations: ['readOnlyHint' => false]),
                    ],
                )]);
            }
        };
    }
}
