<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:01:03 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\DependencyInjection;

use Dmstr\Flowable\DependencyInjection\LiteralMcpSwitchesPass;
use Dmstr\Flowable\FlowableBundle;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The MCP switches must be literal booleans when the container is built,
 * both as parameters and as arguments of the switch decorator. A bare
 * ContainerBuilder with an EnvPlaceholderParameterBag (its default) covers
 * the matrix without compiling a kernel; {@see \Dmstr\Flowable\Tests\Kernel\McpSwitchBuildTest}
 * proves the pass is registered and sees the final values.
 */
final class LiteralMcpSwitchesPassTest extends TestCase
{
    private const DECORATOR = McpToolSwitchResourceMetadataCollectionFactory::class;

    /**
     * @return iterable<string, array{string}>
     */
    public static function switchNames(): iterable
    {
        yield 'read' => ['read'];
        yield 'write' => ['write'];
        yield 'deploy' => ['deploy'];
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function invalidValues(): iterable
    {
        foreach (['read', 'write', 'deploy'] as $switch) {
            $env = '%env(bool:FLOWABLE_MCP_' . strtoupper($switch) . ')%';
            yield $switch . ' env placeholder' => [$switch, $env];
            yield $switch . ' string true' => [$switch, 'true'];
            yield $switch . ' string 1' => [$switch, '1'];
            yield $switch . ' int 1' => [$switch, 1];
            yield $switch . ' null' => [$switch, null];
            yield $switch . ' parameter pointing at a placeholder' => [$switch, '%app.mcp_switch%'];
        }
    }

    public function testBundleRegistersThePass(): void
    {
        $container = new ContainerBuilder();
        (new FlowableBundle())->build($container);

        $passes = array_filter(
            $container->getCompilerPassConfig()->getBeforeRemovingPasses(),
            static fn (object $pass): bool => $pass instanceof LiteralMcpSwitchesPass,
        );

        self::assertCount(1, $passes);
        self::assertSame(PassConfig::TYPE_BEFORE_REMOVING, LiteralMcpSwitchesPass::TYPE);
    }

    public function testRealBooleansAreAccepted(): void
    {
        $container = $this->container(['read' => true, 'write' => false, 'deploy' => true]);
        $container->setDefinition(self::DECORATOR, $this->decorator());
        // A parameter pointing at a bool parameter resolves to a bool
        $container->setParameter('app.mcp_switch', false);
        $container->setParameter('dmstr_flowable.mcp.write', '%app.mcp_switch%');

        (new LiteralMcpSwitchesPass())->process($container);

        $this->addToAssertionCount(1);
    }

    #[DataProvider('invalidValues')]
    public function testNonBooleanParameterIsRejected(string $switch, mixed $value): void
    {
        $container = $this->container();
        $container->setParameter('app.mcp_switch', '%env(bool:APP_MCP_SWITCH)%');
        $container->setParameter('dmstr_flowable.mcp.' . $switch, $value);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The MCP switch "dmstr_flowable.mcp.' . $switch . '" must be a literal boolean');

        (new LiteralMcpSwitchesPass())->process($container);
    }

    public function testMessageExplainsPlaceholderCacheAndRemedy(): void
    {
        $container = $this->container();
        $container->setParameter('dmstr_flowable.mcp.read', '%env(bool:FLOWABLE_MCP_READ)%');

        try {
            (new LiteralMcpSwitchesPass())->process($container);
            self::fail('No exception for an env placeholder');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            self::assertStringContainsString('"dmstr_flowable.mcp.read"', $message);
            self::assertStringContainsString('got string "%env(bool:FLOWABLE_MCP_READ)%" (an env placeholder)', $message);
            self::assertStringContainsString('placeholders such as "%env(bool:FLOWABLE_MCP_READ)%" are not supported', $message);
            self::assertStringContainsString('metadata, which is cached', $message);
            self::assertStringContainsString('cache:clear', $message);
        }
    }

    #[DataProvider('switchNames')]
    public function testTamperedDecoratorArgumentIsRejected(string $switch): void
    {
        $argument = '$' . $switch . 'Enabled';
        foreach (['%env(bool:FLOWABLE_MCP_X)%', 'false'] as $value) {
            $container = $this->container();
            $container->setDefinition(self::DECORATOR, $this->decorator()->setArgument($argument, $value));

            try {
                (new LiteralMcpSwitchesPass())->process($container);
                self::fail('No exception for ' . $argument . ' = ' . $value);
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('The argument ' . $argument . ' of service "' . self::DECORATOR . '"', $e->getMessage());
                self::assertStringContainsString('"dmstr_flowable.mcp.' . $switch . '"', $e->getMessage());
            }
        }
    }

    public function testTamperedPositionalDecoratorArgumentIsRejected(): void
    {
        // After ResolveNamedArgumentsPass the arguments are positional; the
        // pass runs after it
        $container = $this->container();
        $container->setParameter('app.mcp_switch', '%env(bool:FLOWABLE_MCP_X)%');
        $container->setDefinition(self::DECORATOR, new Definition(self::DECORATOR, [
            new Reference('.inner'), true, '%app.mcp_switch%', false,
        ]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The argument $writeEnabled of service');

        (new LiteralMcpSwitchesPass())->process($container);
    }

    public function testDecoratorArgumentsResolvingToBooleansAreAccepted(): void
    {
        $container = $this->container(['read' => true, 'write' => true, 'deploy' => false]);
        $container->setDefinition(self::DECORATOR, $this->decorator()->setArgument('$deployEnabled', true));
        (new LiteralMcpSwitchesPass())->process($container);

        // An application that replaced the service id by an alias of its own
        // definition, with positional arguments
        $container = $this->container();
        $container->setDefinition('app.mcp_switch_factory', new Definition(self::DECORATOR, [new Reference('.inner'), true, false, true]));
        $container->setAlias(self::DECORATOR, 'app.mcp_switch_factory');
        (new LiteralMcpSwitchesPass())->process($container);

        $container->getDefinition('app.mcp_switch_factory')->replaceArgument(3, 'true');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The argument $deployEnabled of service');
        (new LiteralMcpSwitchesPass())->process($container);
    }

    public function testAbsentDecoratorAndParametersLeaveNothingToCheck(): void
    {
        // E.g. an application that removed the decorator: the switches have
        // no effect then, so there is nothing to protect
        (new LiteralMcpSwitchesPass())->process(new ContainerBuilder());

        $container = $this->container();
        $container->setDefinition('unrelated', new Definition(\stdClass::class, ['%env(bool:FLOWABLE_MCP_X)%']));
        (new LiteralMcpSwitchesPass())->process($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @param array{read?: bool, write?: bool, deploy?: bool} $switches
     */
    private function container(array $switches = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach (['read', 'write', 'deploy'] as $switch) {
            $container->setParameter('dmstr_flowable.mcp.' . $switch, $switches[$switch] ?? false);
        }

        return $container;
    }

    /** The definition as config/services.yaml declares it, before ResolveNamedArgumentsPass. */
    private function decorator(): Definition
    {
        return (new Definition(self::DECORATOR))
            ->setDecoratedService('api_platform.metadata.resource.metadata_collection_factory')
            ->setArguments([
                '$decorated' => new Reference('.inner'),
                '$readEnabled' => '%dmstr_flowable.mcp.read%',
                '$writeEnabled' => '%dmstr_flowable.mcp.write%',
                '$deployEnabled' => '%dmstr_flowable.mcp.deploy%',
                '$flowableLogger' => new Reference('monolog.logger.flowable'),
            ]);
    }
}
