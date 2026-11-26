<?php
// file generated with AI assistance: Claude Code - 2026-11-26 09:41:59 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Service;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\McpTool;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use PHPUnit\Framework\TestCase;

final class McpToolInputSchemaFactoryTest extends TestCase
{
    public function testServesTheInputFileForABundleResource(): void
    {
        $inner = $this->createMock(SchemaFactoryInterface::class);
        $inner->expects($this->never())->method('buildSchema');

        $schema = (new McpToolInputSchemaFactory($inner))
            ->buildSchema('Dmstr\\Flowable\\ApiResource\\FlowTask', 'json', Schema::TYPE_INPUT, self::tool('FlowTask/mcpList.input.json'));

        self::assertArrayHasKey('apiConfiguration', $schema['properties']);
    }

    public function testForeignResourceWithTheExtraKeyPassesThrough(): void
    {
        $passed = new Schema();
        $inner = $this->createMock(SchemaFactoryInterface::class);
        $inner->expects($this->once())->method('buildSchema')
            ->with('App\\ApiResource\\Document', 'json', Schema::TYPE_INPUT)
            ->willReturn($passed);

        $schema = (new McpToolInputSchemaFactory($inner))
            ->buildSchema('App\\ApiResource\\Document', 'json', Schema::TYPE_INPUT, self::tool('FlowTask/mcpList.input.json'));

        self::assertSame($passed, $schema);
    }

    public function testDotDotInTheInputPathIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must not contain ".."');

        // Names an existing JSON object file outside src/ApiResource.
        McpToolInputSchemaFactory::inputSchema(self::tool('../Schema/api-configuration.json'));
    }

    public function testPathRejectsDotDotAnywhere(): void
    {
        foreach (['../x.json', 'FlowTask/../FlowTask/complete.input.json', 'FlowTask/..', '..\\x.json'] as $input) {
            try {
                McpToolInputSchemaFactory::path($input);
                self::fail('accepted '.$input);
            } catch (\RuntimeException $e) {
                self::assertStringContainsString('must not contain ".."', $e->getMessage(), $input);
            }
        }
        self::assertStringEndsWith('/ApiResource/FlowTask/complete.input.json', McpToolInputSchemaFactory::path('FlowTask/complete.input.json'));
    }

    private static function tool(string $input): McpTool
    {
        return new McpTool(name: 't', extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => $input]]);
    }
}
