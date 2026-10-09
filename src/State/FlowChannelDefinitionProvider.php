<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowChannelDefinition;

/**
 * @implements ProviderInterface<FlowChannelDefinition>
 */
final class FlowChannelDefinitionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = [
        'key', 'keyLike', 'name', 'nameLike',
        'category', 'categoryLike', 'categoryNotEquals',
        'version', 'latest', 'resourceName',
        'implementation', 'onlyInbound', 'onlyOutbound',
        'createTimeAfter', 'createTimeBefore',
        'deploymentId', 'parentDeploymentId', 'tenantId',
    ];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client($context);

        if ($operation instanceof CollectionOperationInterface) {
            // Newest first — channel definitions, unlike event definitions, do
            // carry a createTime the engine accepts as a sort field.
            $query = array_merge(
                $this->relationFilters(['eventDeployment' => 'deploymentId'], $context),
                $this->listQuery(self::FILTERS, 'createTime', context: $context),
            );

            return $this->paginate($client->listChannelDefinitions($query), FlowChannelDefinition::fromApi(...));
        }

        $data = $client->findChannelDefinition((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowChannelDefinition::fromApi($data) : null;
    }
}
