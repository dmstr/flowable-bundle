<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowProcessDefinition;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\McpToolInputValidationProvider;
use Dmstr\Flowable\State\McpToolInputValidator;
use Dmstr\Flowable\Tests\Fixtures\ForeignMcp\ForeignMcpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The decision rule of the central MCP tool input validation: validate
 * exactly the calls of the bundle's resources that carry MCP arguments,
 * fail-closed, and hand everything else to the inner provider untouched.
 *
 * The validator is the real one (it is final); "not validated" is shown with
 * arguments or operations it would reject.
 */
final class McpToolInputValidationProviderTest extends StateTestCase
{
    private const string LIST_TOOL = 'flowable_list_process_definitions';

    /** @var \ArrayObject<int, array{Operation, array<string, mixed>}> calls of the inner provider */
    private \ArrayObject $innerCalls;

    /** What the inner provider returns */
    private \stdClass $result;

    protected function setUp(): void
    {
        parent::setUp();
        $this->innerCalls = new \ArrayObject();
        $this->result = new \stdClass();
    }

    public function testWithoutMcpArgumentsItOnlyPassesThrough(): void
    {
        // A bundle operation without a schema would be rejected if validated
        $operation = (new GetCollection())->withClass(FlowProcessDefinition::class);

        foreach ([[], ['request' => null, 'resource_class' => FlowProcessDefinition::class], ['mcp_request' => new \stdClass()]] as $context) {
            self::assertSame($this->result, $this->provider()->provide($operation, [], $context));
        }
        self::assertCount(3, $this->innerCalls);
    }

    public function testValidArgumentsOfABundleToolReachTheInnerProvider(): void
    {
        $tool = self::mcpTool(self::LIST_TOOL);
        $context = self::mcpContext(['latest' => true, 'page' => 2]);

        self::assertSame($this->result, $this->provider()->provide($tool, ['x' => 'y'], $context));
        self::assertSame([[$tool, $context]], $this->innerCalls->getArrayCopy());
    }

    public function testInvalidArgumentsOfABundleToolAreRejectedBeforeTheInnerProvider(): void
    {
        try {
            $this->provider()->provide(self::mcpTool(self::LIST_TOOL), [], self::mcpContext(['page' => '2']));
            self::fail('invalid arguments were accepted');
        } catch (UnprocessableEntityHttpException $e) {
            self::assertSame('Input validation failed: {"/page":["The data (string) must match the type: integer"]}', $e->getMessage());
        }
        self::assertCount(0, $this->innerCalls);
    }

    /**
     * @return iterable<string, array{Operation, string}>
     */
    public static function unvalidatableBundleOperations(): iterable
    {
        yield 'McpTool without input schema' => [
            (new McpTool(name: 'flowable_no_schema'))->withClass(FlowProcessDefinition::class),
            'declares no input schema',
        ];
        yield 'McpTool whose schema cannot be built' => [
            (new McpTool(name: 'flowable_broken', extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowProcessDefinition/missing.input.json']]))
                ->withClass(FlowProcessDefinition::class),
            'cannot be built',
        ];
        yield 'McpTool with a path leaving src/ApiResource' => [
            (new McpTool(name: 'flowable_escape', extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => '../FlowableBundle.php']]))
                ->withClass(FlowProcessDefinition::class),
            'cannot be built',
        ];
        yield 'not an McpTool' => [
            (new GetCollection(name: 'flow_process_definition_list'))->withClass(FlowProcessDefinition::class),
            'declares no input schema',
        ];
    }

    #[DataProvider('unvalidatableBundleOperations')]
    public function testBundleOperationWithoutUsableSchemaIsRejected(Operation $operation, string $message): void
    {
        try {
            $this->provider()->provide($operation, [], self::mcpContext([]));
            self::fail('the call was let through unvalidated');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('MCP call rejected', $e->getMessage());
            self::assertStringContainsString($message, $e->getMessage());
        }
        self::assertCount(0, $this->innerCalls);
    }

    public function testNonArrayArgumentsOfABundleToolAreRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('are not an object');

        try {
            $this->provider()->provide(self::mcpTool(self::LIST_TOOL), [], ['mcp_data' => 'latest=true']);
        } finally {
            self::assertCount(0, $this->innerCalls);
        }
    }

    public function testToolOfAnotherNamespaceIsNotValidated(): void
    {
        // Carries the bundle's schema key with a strict file, and no schema
        // would be found at all for the GetCollection: both would be rejected
        $tool = (new McpTool(name: ForeignMcpTool::TOOL, extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowProcessDefinition/mcpList.input.json']]))
            ->withClass(ForeignMcpTool::class);
        $context = self::mcpContext(['unknown' => true, 'page' => '2']);

        self::assertSame($this->result, $this->provider()->provide($tool, [], $context));
        self::assertSame($this->result, $this->provider()->provide((new GetCollection())->withClass(ForeignMcpTool::class), [], $context));
        self::assertCount(2, $this->innerCalls);
    }

    private function provider(): McpToolInputValidationProvider
    {
        $inner = new class ($this->innerCalls, $this->result) implements ProviderInterface {
            /** @param \ArrayObject<int, array{Operation, array<string, mixed>}> $calls */
            public function __construct(private readonly \ArrayObject $calls, private readonly \stdClass $result)
            {
            }

            public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
            {
                $this->calls[] = [$operation, $context];

                return $this->result;
            }
        };

        return new McpToolInputValidationProvider($inner, new McpToolInputValidator());
    }
}
