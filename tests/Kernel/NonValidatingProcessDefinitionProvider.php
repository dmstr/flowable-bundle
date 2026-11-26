<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowProcessDefinition;
use Dmstr\Flowable\State\FlowProcessDefinitionProvider;

/**
 * An application's replacement of a bundle read provider, as the
 * `mcp_fixtures` option of {@see TestKernel} installs it: it decorates
 * {@see FlowProcessDefinitionProvider} without calling it, validates nothing
 * and answers every call with one canned definition
 * ({@see self::CANNED_KEY}), recording the context it was called with.
 *
 * @implements ProviderInterface<FlowProcessDefinition>
 */
final class NonValidatingProcessDefinitionProvider implements ProviderInterface
{
    public const string CANNED_KEY = 'canned-by-replacement';

    /** @var list<array{operation: string, mcp_data: mixed}> one entry per call */
    public array $calls = [];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->calls[] = [
            'operation' => (string) $operation->getName(),
            'mcp_data' => $context['mcp_data'] ?? null,
        ];

        $definition = new FlowProcessDefinition();
        $definition->id = self::CANNED_KEY . ':1:1';
        $definition->key = self::CANNED_KEY;

        return [$definition];
    }
}
