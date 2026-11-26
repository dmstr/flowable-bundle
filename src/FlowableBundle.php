<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable;

use Dmstr\Flowable\DependencyInjection\LiteralMcpSwitchesPass;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Reusable Flowable BPMN engine pass-through bundle.
 *
 * Self-wiring: ships its own DI service definitions (loadExtension) and its
 * own compiler passes (build), and needs no entry in the consuming
 * application's services.yaml.
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
 *             deploy: false # also publish the deploy tools (needs write)
 *
 * All switches default to off, so installing symfony/mcp-bundle in the
 * application exposes no Flowable tool until it is enabled explicitly (see
 * Metadata\McpToolSwitchResourceMetadataCollectionFactory). Each switch must
 * be a literal boolean when the container is built; an env placeholder or a
 * string fails the build (see DependencyInjection\LiteralMcpSwitchesPass).
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
                        ->booleanNode('deploy')
                            ->info('Also publish the deploy tools (flowable_deploy_bundle, flowable_dmn_deploy); needs write. Deployed BPMN can run scripts on the engine.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{mcp: array{read: bool, write: bool, deploy: bool}} $config
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
        $builder->setParameter('dmstr_flowable.mcp.deploy', $config['mcp']['deploy']);
        $container->import(\dirname(__DIR__).'/config/services.yaml');

        // External worker handlers are discovered by interface, so a consuming
        // application writes a class implementing ExternalWorkerHandlerInterface
        // and is done — no #[AutoconfigureTag], no services.yaml entry. The
        // registry consumes the tag (see config/services.yaml).
        $builder->registerForAutoconfiguration(ExternalWorkerHandlerInterface::class)
            ->addTag('flowable.external_worker_handler');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Checks the final switch values, after the application's
        // configuration and parameters are merged (loadExtension() sees only
        // the bundle's own configuration).
        $container->addCompilerPass(
            new LiteralMcpSwitchesPass(),
            LiteralMcpSwitchesPass::TYPE,
            LiteralMcpSwitchesPass::PRIORITY,
        );
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
// - revised 2026-10-09 (mcp.deploy switch)
// - revised 2026-11-26 (compiler pass rejecting non-boolean MCP switches)
