<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class McpToolSwitchResourceMetadataCollectionFactoryTest extends TestCase
{
    private const FLOWABLE_RESOURCE = 'Dmstr\\Flowable\\ApiResource\\FlowTask';

    private const OTHER_RESOURCE = 'App\\ApiResource\\Document';

    /**
     * @return iterable<string, array{bool, bool, bool, list<string>}>
     */
    public static function switches(): iterable
    {
        yield 'all' => [true, true, true, ['flow_task_list', 'flow_task_complete', 'flow_task_claim', 'flow_task_deploy']];
        yield 'read and write' => [true, true, false, ['flow_task_list', 'flow_task_complete', 'flow_task_claim']];
        yield 'read only' => [true, false, false, ['flow_task_list']];
        yield 'write only' => [false, true, false, ['flow_task_complete', 'flow_task_claim']];
        yield 'write and deploy' => [false, true, true, ['flow_task_complete', 'flow_task_claim', 'flow_task_deploy']];
        // Deploy tools need write as well.
        yield 'read and deploy' => [true, false, true, ['flow_task_list']];
        yield 'neither' => [false, false, false, []];
    }

    /**
     * @return iterable<string, array{bool, bool, bool}>
     */
    public static function switchCombinations(): iterable
    {
        foreach (self::switches() as $name => [$read, $write, $deploy]) {
            yield $name => [$read, $write, $deploy];
        }
    }

    /**
     * @param list<string> $expectedTools
     */
    #[DataProvider('switches')]
    public function testSwitchesFilterFlowableTools(bool $read, bool $write, bool $deploy, array $expectedTools): void
    {
        $factory = new McpToolSwitchResourceMetadataCollectionFactory($this->inner(), $read, $write, $deploy);

        $resource = $factory->create(self::FLOWABLE_RESOURCE)[0];

        self::assertSame($expectedTools, array_keys(self::tools($resource)));
        // A removed tool is no operation at all: not in the mcp list, not
        // among the HTTP operations.
        self::assertSame(
            $write ? ['flow_task_get', 'flow_task_hidden_tool_as_operation'] : ['flow_task_get'],
            array_keys(iterator_to_array($resource->getOperations())),
        );
        // McpResource entries of the bundle follow the read switch.
        $resources = array_keys(array_filter($resource->getMcp() ?? [], static fn (object $op): bool => $op instanceof McpResource));
        self::assertSame($read ? ['flow_task_resource'] : [], $resources);
    }

    #[DataProvider('switchCombinations')]
    public function testOtherResourcesStayUntouched(bool $read, bool $write, bool $deploy): void
    {
        $inner = $this->inner();
        $factory = new McpToolSwitchResourceMetadataCollectionFactory($inner, $read, $write, $deploy);

        $resource = $factory->create(self::OTHER_RESOURCE)[0];

        self::assertSame(
            ['doc_search', 'doc_delete'],
            array_keys(self::tools($resource)),
        );
        self::assertSame($inner->create(self::OTHER_RESOURCE)[0]->getMcp(), $resource->getMcp());
        // An McpResource of another bundle or the application is never
        // governed by the bundle's read switch.
        self::assertInstanceOf(McpResource::class, $resource->getMcp()['doc_resource'] ?? null);
    }

    public function testBundleMcpResourceAmongTheHttpOperationsFollowsTheReadSwitch(): void
    {
        $inner = new class implements ResourceMetadataCollectionFactoryInterface {
            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [new ApiResource(
                    class: $resourceClass,
                    operations: [
                        'get' => new Get(name: 'get'),
                        'misplaced_resource' => new McpResource(uri: 'resource://flowable/misplaced', name: 'misplaced_resource'),
                    ],
                )]);
            }
        };

        foreach ([[true, ['get', 'misplaced_resource']], [false, ['get']]] as [$read, $expected]) {
            $resource = (new McpToolSwitchResourceMetadataCollectionFactory($inner, $read, true, true))->create(self::FLOWABLE_RESOURCE)[0];

            self::assertSame($expected, array_keys(iterator_to_array($resource->getOperations())), $read ? 'read on' : 'read off');
        }
    }

    public function testOnlyLiteralTrueReadOnlyHintCountsAsRead(): void
    {
        self::assertTrue(McpToolSwitchResourceMetadataCollectionFactory::isReadOnly(
            new McpTool(name: 'a', annotations: ['readOnlyHint' => true]),
        ));
        self::assertTrue(McpToolSwitchResourceMetadataCollectionFactory::isReadOnly(
            new McpTool(name: 'b', annotations: (object) ['readOnlyHint' => true]),
        ));
        foreach ([null, [], ['readOnlyHint' => false], ['readOnlyHint' => 'true'], ['readOnlyHint' => 1], ['destructiveHint' => false]] as $annotations) {
            self::assertFalse(
                McpToolSwitchResourceMetadataCollectionFactory::isReadOnly(new McpTool(name: 'c', annotations: $annotations)),
                json_encode($annotations),
            );
        }
    }

    /**
     * @return array<string, McpTool>
     */
    private static function tools(ApiResource $resource): array
    {
        return array_filter($resource->getMcp() ?? [], static fn (object $op): bool => $op instanceof McpTool);
    }

    private function inner(): ResourceMetadataCollectionFactoryInterface
    {
        $flowable = new ApiResource(
            class: self::FLOWABLE_RESOURCE,
            operations: [
                'flow_task_get' => new Get(name: 'flow_task_get'),
                // Defensive case: a tool registered among the HTTP operations.
                'flow_task_hidden_tool_as_operation' => new McpTool(name: 'flow_task_hidden_tool_as_operation'),
            ],
            mcp: [
                'flow_task_list' => new McpTool(name: 'flow_task_list', annotations: ['readOnlyHint' => true]),
                'flow_task_complete' => new McpTool(name: 'flow_task_complete', annotations: ['readOnlyHint' => false, 'destructiveHint' => false]),
                // No annotations at all: counts as a write tool (fail-closed).
                'flow_task_claim' => new McpTool(name: 'flow_task_claim'),
                'flow_task_deploy' => new McpTool(
                    name: 'flow_task_deploy',
                    annotations: ['readOnlyHint' => false, 'destructiveHint' => true],
                    extraProperties: [McpToolSwitchResourceMetadataCollectionFactory::SWITCH_KEY => 'deploy'],
                ),
                'flow_task_resource' => new McpResource(uri: 'resource://flowable/tasks', name: 'flow_task_resource'),
            ],
        );
        $other = new ApiResource(
            class: self::OTHER_RESOURCE,
            mcp: [
                'doc_search' => new McpTool(name: 'doc_search', annotations: ['readOnlyHint' => true]),
                'doc_delete' => new McpTool(name: 'doc_delete'),
                'doc_resource' => new McpResource(uri: 'resource://app/documents', name: 'doc_resource'),
            ],
        );

        return new class([self::FLOWABLE_RESOURCE => $flowable, self::OTHER_RESOURCE => $other]) implements ResourceMetadataCollectionFactoryInterface {
            /** @param array<string, ApiResource> $resources */
            public function __construct(private readonly array $resources)
            {
            }

            public function create(string $resourceClass): ResourceMetadataCollection
            {
                return new ResourceMetadataCollection($resourceClass, [$this->resources[$resourceClass]]);
            }
        };
    }
}
// - revised 2026-10-09 (mcp.deploy switch)
// - revised 2026-11-26 (McpResource entries follow mcp.read)
