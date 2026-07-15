<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowTask;

/**
 * @implements ProviderInterface<FlowTask>
 */
final class FlowTaskProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['processInstanceId', 'assignee', 'taskDefinitionKey'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters([
                    'processInstance' => 'processInstanceId',
                    'processDefinition' => 'processDefinitionId',
                ]),
                $this->listQuery(self::FILTERS, 'createTime'),
            );
            $envelope = $client->listTasks($query);

            return $this->paginate($envelope, FlowTask::fromApi(...));
        }

        $data = $client->findTask((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowTask::fromApi($data) : null;
    }
}
