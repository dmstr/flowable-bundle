<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\DependencyInjection;

use Dmstr\Flowable\State\McpToolInputValidationProvider;
use Dmstr\Flowable\State\McpToolInputValidator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Hooks {@see McpToolInputValidationProvider}, the central validation of the
 * bundle's MCP tool arguments, into the provider chain API Platform's MCP
 * handler calls.
 *
 * API Platform has two layouts of that chain:
 *  - `use_symfony_listeners: true`: a chain of its own for MCP, whose read
 *    step is {@see self::MCP_READ} (mcp/events.php);
 *  - the default: `api_platform.mcp.state_provider` is an alias of the main
 *    chain `api_platform.state_provider.main`, whose read step is
 *    {@see self::MAIN_READ} (state/provider.php).
 * The decorator decorates {@see self::MCP_READ} when that service exists,
 * otherwise {@see self::MAIN_READ}, with {@see self::DECORATION_PRIORITY}:
 * Symfony applies the decorator with the highest priority first, so it is
 * the innermost one. API Platform's `pre_read` access check decorates the
 * same service with 10 and the check after reading with 0, hence the call
 * order `pre_read` check → validation → ReadProvider → check after reading.
 *
 * Fail-closed at build time:
 *  - no `api_platform.mcp.handler` (symfony/mcp-bundle missing or API
 *    Platform's MCP support disabled): no tool can be called, nothing is
 *    registered;
 *  - the handler exists but neither read service does: the container build
 *    fails, so the bundle's tools are never served unvalidated.
 *
 * A compiler pass and not config/services.yaml, because whether the handler
 * exists is known only after all extensions are loaded. Registered as
 * {@see self::TYPE} so that Symfony's DecoratorServicePass, an optimization
 * pass, still processes the decoration declared here. The definition is
 * built here, so the `_defaults` of services.yaml (autowire, bind) do not
 * apply; its arguments are set explicitly.
 */
final class McpToolInputValidationPass implements CompilerPassInterface
{
    public const string TYPE = PassConfig::TYPE_BEFORE_OPTIMIZATION;

    public const int PRIORITY = 0;

    public const string SERVICE_ID = McpToolInputValidationProvider::class;

    public const string MCP_HANDLER = 'api_platform.mcp.handler';

    public const string MCP_READ = 'api_platform.mcp.state_provider.read';

    public const string MAIN_READ = 'api_platform.state_provider.read';

    public const int DECORATION_PRIORITY = 20;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::MCP_HANDLER)) {
            return;
        }

        $target = match (true) {
            $container->has(self::MCP_READ) => self::MCP_READ,
            $container->has(self::MAIN_READ) => self::MAIN_READ,
            default => throw new LogicException(sprintf(
                'The central validation of the MCP tool arguments of dmstr/flowable-bundle has no place to hook in: '
                . 'API Platform\'s MCP handler "%s" is registered, but neither of the read providers "%s" '
                . '(use_symfony_listeners: true) and "%s" (default) exists. The bundle\'s tools would run '
                . 'without validating their arguments, so the container is not built. This API Platform '
                . 'version is not supported by the bundle.',
                self::MCP_HANDLER,
                self::MCP_READ,
                self::MAIN_READ,
            )),
        };

        $container->setDefinition(self::SERVICE_ID, (new Definition(McpToolInputValidationProvider::class))
            ->setDecoratedService($target, null, self::DECORATION_PRIORITY)
            ->setArguments([
                new Reference(self::SERVICE_ID . '.inner'),
                new Reference(McpToolInputValidator::class),
            ]));
    }
}
