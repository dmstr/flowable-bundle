<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\DependencyInjection\McpToolInputValidationPass;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;

/**
 * Validates the arguments of every MCP tool call of this bundle's resources
 * in one place, in front of whatever provider serves the tool, so a tool
 * stays validated when an application replaces the bundle's provider by one
 * of its own.
 *
 * Decorates the READ provider of the provider chain the MCP handler calls
 * (registered by {@see McpToolInputValidationPass}):
 * `api_platform.mcp.state_provider.read` with `use_symfony_listeners: true`,
 * otherwise `api_platform.state_provider.read`, the main chain every HTTP
 * request of the application passes as well. With decoration priority 20 it
 * wraps the ReadProvider directly, inside the `pre_read` access check
 * (priority 10) and the access check after reading (priority 0), so a call
 * runs: `pre_read` security check → this validation → ReadProvider (the
 * tool's provider) → security check after reading.
 *
 * Decision rule, cheapest check first:
 *  - no `mcp_data` in the context: pass through, no schema work. Only a
 *    tool call carries it; HTTP requests, the CLI and MCP resource reads
 *    (`mcp_request` without `mcp_data`) do not;
 *  - the operation's class is not a resource of this bundle
 *    ({@see McpToolSwitchResourceMetadataCollectionFactory::isBundleResource()},
 *    the rule the tool switches and the input schema factory use): pass
 *    through, also when the tool declares an input schema;
 *  - otherwise {@see McpToolInputValidator} validates the arguments against
 *    the schema the tool publishes and rejects the call on a violation (422);
 *    fail-closed, it is also rejected when the arguments are not an array,
 *    the operation is not an McpTool, declares no input schema or its schema
 *    cannot be built.
 *
 * Write tools pass this chain too, so their arguments are first checked
 * against the published schema here and then against the full input file by
 * their processor.
 *
 * Known limitation: the validation runs after the `pre_read` security check
 * and before reading. A tool whose `security` expression uses `object` is
 * checked against the object only after reading, so a caller who would be
 * denied by that object check still learns whether its arguments are valid.
 */
final class McpToolInputValidationProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $decorated
     */
    public function __construct(
        private readonly ProviderInterface $decorated,
        private readonly McpToolInputValidator $validator,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Only a tool call carries mcp_data (API Platform's MCP handler sets
        // it next to mcp_request; an MCP resource read has mcp_request only)
        if (\array_key_exists('mcp_data', $context)
            && McpToolSwitchResourceMetadataCollectionFactory::isBundleResource((string) $operation->getClass())
        ) {
            if (!\is_array($context['mcp_data'])) {
                throw new \RuntimeException(sprintf('MCP call rejected: the arguments of tool "%s" are not an object.', (string) $operation->getName()));
            }
            $this->validator->validate($operation, $context);
        }

        return $this->decorated->provide($operation, $uriVariables, $context);
    }
}
