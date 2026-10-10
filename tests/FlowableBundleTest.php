<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests;

use Dmstr\Flowable\FlowableBundle;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class FlowableBundleTest extends TestCase
{
    public function testConfigRootIsDmstrFlowable(): void
    {
        self::assertSame('dmstr_flowable', (new FlowableBundle())->getContainerExtension()?->getAlias());
    }

    public function testMcpSwitchesDefaultToOff(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->getParameter('dmstr_flowable.mcp.read'));
        self::assertFalse($container->getParameter('dmstr_flowable.mcp.write'));
        self::assertFalse($container->getParameter('dmstr_flowable.mcp.deploy'));
    }

    public function testMcpSwitchesArePassedAsParameters(): void
    {
        $container = $this->load([['mcp' => ['read' => true]], ['mcp' => ['write' => true, 'deploy' => true]]]);

        self::assertTrue($container->getParameter('dmstr_flowable.mcp.read'));
        self::assertTrue($container->getParameter('dmstr_flowable.mcp.write'));
        self::assertTrue($container->getParameter('dmstr_flowable.mcp.deploy'));
    }

    public function testDecoratorIsRegisteredWithTheSwitches(): void
    {
        $definition = $this->load([])->getDefinition(McpToolSwitchResourceMetadataCollectionFactory::class);

        self::assertSame(
            ['api_platform.metadata.resource.metadata_collection_factory', null, 0],
            $definition->getDecoratedService(),
        );
        self::assertSame('%dmstr_flowable.mcp.read%', $definition->getArgument('$readEnabled'));
        self::assertSame('%dmstr_flowable.mcp.write%', $definition->getArgument('$writeEnabled'));
        self::assertSame('%dmstr_flowable.mcp.deploy%', $definition->getArgument('$deployEnabled'));
    }

    public function testMcpInputSchemaFactoryDecoratesTheMcpSchemaFactoryWhenPresent(): void
    {
        $definition = $this->load([])->getDefinition(McpToolInputSchemaFactory::class);

        self::assertSame(
            ['api_platform.mcp.json_schema.schema_factory', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE],
            $definition->getDecoratedService(),
        );
    }

    /**
     * @param list<array<string,mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.environment' => 'test',
            'kernel.build_dir' => sys_get_temp_dir(),
        ]));
        $extension = (new FlowableBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load($configs, $container);

        return $container;
    }
}
// - revised 2026-10-08 (MCP input schema factory registration)
// - revised 2026-10-09 (mcp.deploy switch)
