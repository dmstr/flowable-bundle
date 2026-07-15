<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowHistoricProcessInstance;

/**
 * @implements ProviderInterface<FlowHistoricProcessInstance>
 */
final class FlowHistoricProcessInstanceProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['finished', 'processDefinitionKey', 'processDefinitionId', 'businessKey', 'startedBy', 'finishedAfter', 'finishedBefore'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters(['processDefinition' => 'processDefinitionId']),
                $this->listQuery(self::FILTERS, 'startTime'),
            );
            $envelope = $client->listHistoricProcessInstances($query);

            return $this->paginate($envelope, FlowHistoricProcessInstance::fromApi(...));
        }

        $data = $client->findHistoricProcessInstance((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowHistoricProcessInstance::fromApi($data) : null;
    }
}
