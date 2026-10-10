<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowEventDefinition;

/**
 * @implements ProviderInterface<FlowEventDefinition>
 */
final class FlowEventDefinitionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = [
        'key', 'keyLike', 'name', 'nameLike',
        'category', 'categoryLike', 'categoryNotEquals',
        'version', 'latest', 'resourceName',
        'deploymentId', 'parentDeploymentId', 'tenantId',
    ];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client($context);

        if ($operation instanceof CollectionOperationInterface) {
            // No default sort: event definitions carry no timestamp column
            // (unlike channel definitions), so the engine's own default — name
            // ascending — is the only sensible ordering.
            $query = array_merge(
                $this->relationFilters(['eventDeployment' => 'deploymentId'], $context),
                $this->listQuery(self::FILTERS, context: $context),
            );

            return $this->paginate($client->listEventDefinitions($query), FlowEventDefinition::fromApi(...));
        }

        $data = $client->findEventDefinition((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowEventDefinition::fromApi($data) : null;
    }
}
