<?php
// file generated with AI assistance: Claude Code - 2026-11-26 09:41:59 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Metadata;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * A tool whose input schema cannot be built is removed from the resource
 * metadata (neither listed nor callable, see the factory's class docblock)
 * and logged, in every switch configuration, while the other tools stay.
 */
final class McpToolBrokenInputSchemaTest extends TestCase
{
    private const FLOWABLE_RESOURCE = 'Dmstr\\Flowable\\ApiResource\\FlowTask';

    private const OTHER_RESOURCE = 'App\\ApiResource\\Document';

    /**
     * Input paths relative to src/ApiResource that cannot be built: a missing
     * file, a file that is no JSON (a PHP source), and a path leaving the
     * directory (it names an existing JSON object file).
     *
     * @return iterable<string, array{string}>
     */
    public static function brokenInputs(): iterable
    {
        yield 'missing file' => ['FlowTask/missing.input.json'];
        yield 'invalid JSON' => ['FlowTask.php'];
        yield 'dot-dot in the path' => ['../Schema/api-configuration.json'];
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function brokenInputsAndSwitches(): iterable
    {
        foreach (self::brokenInputs() as $name => [$input]) {
            yield $name.', all switches on' => [$input, true, true, true];
            yield $name.', read and write' => [$input, true, true, false];
            yield $name.', read only' => [$input, true, false, false];
        }
    }

    #[DataProvider('brokenInputsAndSwitches')]
    public function testToolWithBrokenInputSchemaIsHiddenAndLogged(string $input, bool $read, bool $write, bool $deploy): void
    {
        $logger = self::logger();
        $factory = new McpToolSwitchResourceMetadataCollectionFactory(self::inner($input), $read, $write, $deploy, $logger);

        $resource = $factory->create(self::FLOWABLE_RESOURCE)[0];

        $expected = $write ? ['flow_task_list', 'flow_task_complete'] : ['flow_task_list'];
        self::assertSame($expected, array_keys($resource->getMcp() ?? []));
        self::assertSame(['flow_task_get'], array_keys(iterator_to_array($resource->getOperations())));

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame('error', $level);
        self::assertSame('flowable.mcp.tool_hidden', $message);
        self::assertSame('flow_task_broken', $context['tool']);
        self::assertInstanceOf(\RuntimeException::class, $context['exception']);
    }

    public function testSwitchedOffToolIsNotCheckedForItsSchema(): void
    {
        $logger = self::logger();
        $factory = new McpToolSwitchResourceMetadataCollectionFactory(self::inner('FlowTask/missing.input.json', brokenReadOnly: false), true, false, false, $logger);

        $resource = $factory->create(self::FLOWABLE_RESOURCE)[0];

        // The broken tool is a write tool and mcp.write is off: removed by
        // its switch, so there is nothing to report.
        self::assertSame(['flow_task_list'], array_keys($resource->getMcp() ?? []));
        self::assertSame([], $logger->records);
    }

    #[DataProvider('brokenInputs')]
    public function testForeignResourceWithTheExtraKeyIsUntouched(string $input): void
    {
        $logger = self::logger();
        $inner = self::inner($input);
        $factory = new McpToolSwitchResourceMetadataCollectionFactory($inner, true, true, true, $logger);

        $resource = $factory->create(self::OTHER_RESOURCE)[0];

        self::assertSame($inner->create(self::OTHER_RESOURCE)[0]->getMcp(), $resource->getMcp());
        self::assertSame([], $logger->records);
    }

    public function testBundleResourceGuard(): void
    {
        self::assertTrue(McpToolSwitchResourceMetadataCollectionFactory::isBundleResource(self::FLOWABLE_RESOURCE));
        self::assertTrue(McpToolSwitchResourceMetadataCollectionFactory::isBundleResource('\\'.self::FLOWABLE_RESOURCE));
        self::assertFalse(McpToolSwitchResourceMetadataCollectionFactory::isBundleResource(self::OTHER_RESOURCE));
        self::assertFalse(McpToolSwitchResourceMetadataCollectionFactory::isBundleResource('Dmstr\\Flowable\\ApiResourceX\\FlowTask'));
        self::assertFalse(McpToolSwitchResourceMetadataCollectionFactory::isBundleResource('App\\Dmstr\\Flowable\\ApiResource\\FlowTask'));
    }

    /**
     * @return AbstractLogger&object{records: list<array{string, string, array<string, mixed>}>}
     */
    private static function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }

    private static function inner(string $brokenInput, bool $brokenReadOnly = true): ResourceMetadataCollectionFactoryInterface
    {
        $broken = static fn (string $name, bool $readOnly): McpTool => new McpTool(
            name: $name,
            annotations: ['readOnlyHint' => $readOnly],
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => $brokenInput]],
        );
        $flowable = new ApiResource(
            class: self::FLOWABLE_RESOURCE,
            operations: ['flow_task_get' => new Get(name: 'flow_task_get')],
            mcp: [
                'flow_task_list' => new McpTool(
                    name: 'flow_task_list',
                    annotations: ['readOnlyHint' => true],
                    extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowTask/mcpList.input.json']],
                ),
                'flow_task_complete' => new McpTool(
                    name: 'flow_task_complete',
                    annotations: ['readOnlyHint' => false],
                    extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowTask/complete.input.json']],
                ),
                'flow_task_broken' => $broken('flow_task_broken', $brokenReadOnly),
            ],
        );
        $other = new ApiResource(
            class: self::OTHER_RESOURCE,
            mcp: [
                'doc_search' => $broken('doc_search', true),
                'doc_delete' => $broken('doc_delete', false),
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
