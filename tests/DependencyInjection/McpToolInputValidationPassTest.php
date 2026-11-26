<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\DependencyInjection;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\DependencyInjection\McpToolInputValidationPass;
use Dmstr\Flowable\FlowableBundle;
use Dmstr\Flowable\State\McpToolInputValidationProvider;
use Dmstr\Flowable\State\McpToolInputValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Where the central MCP tool input validation is hooked in, on a bare
 * ContainerBuilder with stand-ins for API Platform's services. The real
 * chains of both API Platform layouts are covered by
 * {@see \Dmstr\Flowable\Tests\Http\McpToolInputValidationTest}.
 */
final class McpToolInputValidationPassTest extends TestCase
{
    private const string ID = McpToolInputValidationProvider::class;

    public function testBundleRegistersThePassBeforeOptimization(): void
    {
        $container = new ContainerBuilder();
        (new FlowableBundle())->build($container);

        $passes = array_filter(
            $container->getCompilerPassConfig()->getBeforeOptimizationPasses(),
            static fn (object $pass): bool => $pass instanceof McpToolInputValidationPass,
        );

        self::assertCount(1, $passes);
        self::assertSame(PassConfig::TYPE_BEFORE_OPTIMIZATION, McpToolInputValidationPass::TYPE);
    }

    public function testWithoutTheMcpHandlerNothingIsRegistered(): void
    {
        $container = $this->container(handler: false, mcpRead: true, mainRead: true);

        (new McpToolInputValidationPass())->process($container);

        self::assertFalse($container->hasDefinition(self::ID));
    }

    public function testDecoratesTheMcpReadProviderOfTheListenerLayout(): void
    {
        $container = $this->container(handler: true, mcpRead: true, mainRead: false);

        (new McpToolInputValidationPass())->process($container);

        self::assertSame(['api_platform.mcp.state_provider.read', null, 20], $container->getDefinition(self::ID)->getDecoratedService());
    }

    public function testDecoratesTheMainReadProviderOfTheDefaultLayout(): void
    {
        $container = $this->container(handler: true, mcpRead: false, mainRead: true);

        (new McpToolInputValidationPass())->process($container);

        self::assertSame(['api_platform.state_provider.read', null, 20], $container->getDefinition(self::ID)->getDecoratedService());
    }

    public function testPrefersTheMcpReadProviderWhenBothExist(): void
    {
        $container = $this->container(handler: true, mcpRead: true, mainRead: true);

        (new McpToolInputValidationPass())->process($container);

        self::assertSame('api_platform.mcp.state_provider.read', $container->getDefinition(self::ID)->getDecoratedService()[0]);
    }

    public function testWithTheHandlerAndNoReadProviderTheBuildFails(): void
    {
        $container = $this->container(handler: true, mcpRead: false, mainRead: false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The central validation of the MCP tool arguments of dmstr/flowable-bundle has no place to hook in');

        (new McpToolInputValidationPass())->process($container);
    }

    public function testDefinitionIsCompleteWithoutServiceDefaults(): void
    {
        $container = $this->container(handler: true, mcpRead: false, mainRead: true);

        (new McpToolInputValidationPass())->process($container);
        $definition = $container->getDefinition(self::ID);

        self::assertSame(McpToolInputValidationProvider::class, $definition->getClass());
        self::assertFalse($definition->isAutowired());
        // No fourth element: the decoration fails on a missing service, it is never ignored
        self::assertCount(3, $definition->getDecoratedService());
        self::assertEquals(
            [new Reference(self::ID . '.inner'), new Reference(McpToolInputValidator::class)],
            $definition->getArguments(),
        );
    }

    /**
     * Registered before optimization, the decoration is still processed by
     * Symfony's DecoratorServicePass: after compiling, the read service is the
     * decorator around the original provider.
     */
    public function testDecorationIsAppliedWhenTheContainerIsCompiled(): void
    {
        $container = $this->container(handler: true, mcpRead: false, mainRead: true);
        $container->getDefinition('api_platform.state_provider.read')->setPublic(true);
        $container->addCompilerPass(new McpToolInputValidationPass(), McpToolInputValidationPass::TYPE, McpToolInputValidationPass::PRIORITY);

        $container->compile();
        $provider = $container->get('api_platform.state_provider.read');

        self::assertInstanceOf(McpToolInputValidationProvider::class, $provider);
        self::assertInstanceOf(
            StubReadProvider::class,
            (new \ReflectionProperty(McpToolInputValidationProvider::class, 'decorated'))->getValue($provider),
        );
    }

    private function container(bool $handler, bool $mcpRead, bool $mainRead): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(McpToolInputValidator::class, new Definition(McpToolInputValidator::class));
        if ($handler) {
            $container->setDefinition('api_platform.mcp.handler', new Definition(\stdClass::class));
        }
        if ($mcpRead) {
            $container->setDefinition('api_platform.mcp.state_provider.read', new Definition(StubReadProvider::class));
        }
        if ($mainRead) {
            $container->setDefinition('api_platform.state_provider.read', new Definition(StubReadProvider::class));
        }

        return $container;
    }
}

/**
 * Stand-in for API Platform's ReadProvider.
 *
 * @implements ProviderInterface<object>
 */
final class StubReadProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return null;
    }
}
