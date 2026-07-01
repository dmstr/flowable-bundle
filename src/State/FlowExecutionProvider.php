<?php
// file generated with AI assistance: Claude Code - 2026-06-22 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowExecution;

/**
 * @implements ProviderInterface<FlowExecution>
 */
final class FlowExecutionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['processInstanceId', 'activityId', 'parentId'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters(['processInstance' => 'processInstanceId']),
                $this->listQuery(self::FILTERS),
            );
            $envelope = $client->listExecutions($query);

            return $this->paginate($envelope, FlowExecution::fromApi(...));
        }

        $data = $client->findExecution((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowExecution::fromApi($data) : null;
    }
}
