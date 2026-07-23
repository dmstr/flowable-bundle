<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowDecision;

/**
 * @implements ProviderInterface<FlowDecision>
 */
final class FlowDecisionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['key', 'name', 'category', 'deploymentId', 'latest'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters(['dmnDeployment' => 'deploymentId']),
                $this->listQuery(self::FILTERS),
            );

            return $this->paginate($client->listDecisions($query), FlowDecision::fromApi(...));
        }

        $data = $client->findDecision((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowDecision::fromApi($data) : null;
    }
}
