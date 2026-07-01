<?php
// file generated with AI assistance: Claude Code - 2026-06-18 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowHistoricActivity;

/**
 * @implements ProviderInterface<FlowHistoricActivity>
 */
final class FlowHistoricActivityProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['processInstanceId', 'activityId', 'activityType', 'finished'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if (!$operation instanceof CollectionOperationInterface) {
            return null;
        }

        $query = array_merge(
            $this->relationFilters(['historicProcessInstance' => 'processInstanceId']),
            $this->listQuery(self::FILTERS),
        );
        $envelope = $this->client()->listHistoricActivities($query);

        return $this->paginate($envelope, FlowHistoricActivity::fromApi(...));
    }
}
