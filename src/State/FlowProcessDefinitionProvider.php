<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowProcessDefinition;

/**
 * @implements ProviderInterface<FlowProcessDefinition>
 */
final class FlowProcessDefinitionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['latest', 'key', 'category'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters(['deployment' => 'deploymentId']),
                $this->listQuery(self::FILTERS),
            );
            $envelope = $client->listProcessDefinitions($query);

            return $this->paginate($envelope, FlowProcessDefinition::fromApi(...));
        }

        $data = $client->findProcessDefinition((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowProcessDefinition::fromApi($data) : null;
    }
}
