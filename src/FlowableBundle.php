<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable;

use Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Reusable Flowable BPMN engine pass-through bundle.
 *
 * Self-wiring: ships its own DI service definitions (loadExtension) and needs
 * no entry in the consuming application's services.yaml and no compiler pass.
 *
 * The bundle exposes Doctrine-less API Platform pass-through resources under
 * /api/flowable/*, an HTTP FlowableClient resolved per request from an
 * ApiConfiguration of type "flowable", and matching flowable:* CLI commands.
 *
 * API Platform discovers the ApiResource classes automatically because they
 * live under <bundle>/src/ApiResource and every registered bundle's
 * src/ApiResource directory is scanned (see ApiPlatformExtension), so the
 * application's api_platform.mapping.paths is left untouched.
 *
 * Configuration (config/packages/dmstr_flowable.yaml, all optional):
 *
 *     dmstr_flowable:
 *         mcp:
 *             read: false   # publish read-only McpTool operations
 *             write: false  # publish all other (writing) McpTool operations
 *
 * Both switches default to off, so installing symfony/mcp-bundle in the
 * application exposes no Flowable tool until it is enabled explicitly (see
 * Metadata\McpToolSwitchResourceMetadataCollectionFactory).
 */
final class FlowableBundle extends AbstractBundle
{
    /** Config root; the derived default would be "flowable". */
    protected string $extensionAlias = 'dmstr_flowable';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('mcp')
                    ->info('Publication of the bundle\'s API Platform McpTool operations as MCP tools.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('read')
                            ->info('Publish read-only tools (annotations readOnlyHint: true).')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('write')
                            ->info('Publish all other tools; a tool without readOnlyHint: true counts as writing.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{mcp: array{read: bool, write: bool}} $config
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Absolute bundle directory, so config/services.yaml can locate the
        // bundle's own ApiResource dir regardless of install location
        // (vendor/ vs. a composer path-repo) — never assume %kernel.project_dir%.
        $builder->setParameter('dmstr_flowable.dir', \dirname(__DIR__));
        $builder->setParameter('dmstr_flowable.mcp.read', $config['mcp']['read']);
        $builder->setParameter('dmstr_flowable.mcp.write', $config['mcp']['write']);
        $container->import(\dirname(__DIR__).'/config/services.yaml');

        // External worker handlers are discovered by interface, so a consuming
        // application writes a class implementing ExternalWorkerHandlerInterface
        // and is done — no #[AutoconfigureTag], no services.yaml entry. The
        // registry consumes the tag (see config/services.yaml).
        $builder->registerForAutoconfiguration(ExternalWorkerHandlerInterface::class)
            ->addTag('flowable.external_worker_handler');
    }

    public function prependExtension(
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Dedicated "flowable" Monolog channel for pass-through write auditing
        // (replaces the Gedmo audit log, which would require Doctrine).
        if ($builder->hasExtension('monolog')) {
            $builder->prependExtensionConfig('monolog', ['channels' => ['flowable']]);
        }
    }
}
