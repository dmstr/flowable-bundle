<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:01:03 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\DependencyInjection;

use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Fails the container build when an MCP switch is not a literal boolean.
 *
 * The switches dmstr_flowable.mcp.read / mcp.write / mcp.deploy are applied
 * by {@see McpToolSwitchResourceMetadataCollectionFactory}, which sits inside
 * API Platform's resource metadata cache: the filtered metadata is what gets
 * cached. A value resolved at runtime, such as '%env(bool:FLOWABLE_MCP_READ)%',
 * would be frozen in that cache at warmup, and changing the environment
 * variable later would silently change nothing. Hence a switch must be true
 * or false when the container is built.
 *
 * The configuration tree alone cannot enforce this: its booleanNode accepts
 * env placeholders, and an application can override the parameters after the
 * extension has been loaded. So the pass checks the final values:
 *
 *  - the three parameters, resolved: an env placeholder resolves to a string
 *    (env_<prefix>_bool_<NAME>_<hash>), as do 'true' or '1';
 *  - the switch arguments of the decorator's service definition, in case an
 *    application redefines or modifies it. Nothing is checked when the
 *    service is not defined.
 *
 * Registered as {@see self::TYPE} with {@see self::PRIORITY}: after all
 * extensions and the application configuration are merged and after the
 * optimization passes of every bundle, so the definition is the final one
 * (its named arguments are positional by then), while parameters and env
 * placeholders are still unresolved enough to be told apart.
 */
final class LiteralMcpSwitchesPass implements CompilerPassInterface
{
    public const string TYPE = PassConfig::TYPE_BEFORE_REMOVING;

    public const int PRIORITY = -1000;

    /** Switch name => constructor argument of the decorator and its position. */
    private const array SWITCHES = [
        'read' => ['$readEnabled', 1],
        'write' => ['$writeEnabled', 2],
        'deploy' => ['$deployEnabled', 3],
    ];

    private const string DECORATOR = McpToolSwitchResourceMetadataCollectionFactory::class;

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SWITCHES as $switch => [$argumentName, $position]) {
            $parameter = 'dmstr_flowable.mcp.' . $switch;
            if ($container->hasParameter($parameter)) {
                $this->assertBoolean(
                    $container,
                    $container->getParameter($parameter),
                    sprintf('The MCP switch "%s"', $parameter),
                    $parameter,
                );
            }
        }

        if (!$container->has(self::DECORATOR)) {
            return;
        }
        $arguments = $container->findDefinition(self::DECORATOR)->getArguments();
        foreach (self::SWITCHES as $switch => [$argumentName, $position]) {
            foreach ([$argumentName, $position] as $key) {
                if (\array_key_exists($key, $arguments)) {
                    $this->assertBoolean(
                        $container,
                        $arguments[$key],
                        sprintf('The argument %s of service "%s"', $argumentName, self::DECORATOR),
                        'dmstr_flowable.mcp.' . $switch,
                    );
                }
            }
        }
    }

    private function assertBoolean(ContainerBuilder $container, mixed $value, string $subject, string $parameter): void
    {
        $resolved = \is_string($value) || \is_array($value)
            ? $container->getParameterBag()->resolveValue($value)
            : $value;
        if (\is_bool($resolved)) {
            return;
        }

        $usedEnvs = [];
        $readable = $container->resolveEnvPlaceholders($resolved, '%%env(%s)%%', $usedEnvs);

        throw new InvalidArgumentException(sprintf(
            '%s must be a literal boolean (true or false) when the container is built, got %s%s%s. '
            . 'Environment variable placeholders such as "%%env(bool:FLOWABLE_MCP_%s)%%" are not supported: '
            . 'the switch filters API Platform\'s resource metadata, which is cached, so a value resolved at runtime '
            . 'would be frozen in that cache and changing it would have no effect. '
            . 'Set true or false in the configuration, e.g. per environment in config/packages/<env>/dmstr_flowable.yaml '
            . '(or the "%s" parameter of the application), and run "bin/console cache:clear" after changing it.',
            $subject,
            get_debug_type($resolved),
            \is_scalar($readable) ? sprintf(' "%s"', $readable) : '',
            $usedEnvs !== [] ? ' (an env placeholder)' : '',
            strtoupper(substr($parameter, \strlen('dmstr_flowable.mcp.'))),
            $parameter,
        ));
    }
}
