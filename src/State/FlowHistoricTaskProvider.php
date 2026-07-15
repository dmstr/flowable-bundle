<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowHistoricTask;

/**
 * @implements ProviderInterface<FlowHistoricTask>
 */
final class FlowHistoricTaskProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['processInstanceId', 'finished', 'taskDefinitionKey', 'taskAssignee', 'processDefinitionKey'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters([
                    'historicProcessInstance' => 'processInstanceId',
                    'processDefinition' => 'processDefinitionId',
                ]),
                $this->listQuery(self::FILTERS, 'endTime'),
            );
            $envelope = $client->listHistoricTasks($query);

            return $this->paginate($envelope, FlowHistoricTask::fromApi(...));
        }

        $data = $client->findHistoricTask((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowHistoricTask::fromApi($data) : null;
    }
}
