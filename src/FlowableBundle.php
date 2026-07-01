<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable;

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
 */
final class FlowableBundle extends AbstractBundle
{
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        // Absolute bundle directory, so config/services.yaml can locate the
        // bundle's own ApiResource dir regardless of install location
        // (vendor/ vs. a composer path-repo) — never assume %kernel.project_dir%.
        $builder->setParameter('dmstr_flowable.dir', \dirname(__DIR__));
        $container->import(\dirname(__DIR__).'/config/services.yaml');
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
