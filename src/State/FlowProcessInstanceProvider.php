<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowProcessInstance;

/**
 * @implements ProviderInterface<FlowProcessInstance>
 */
final class FlowProcessInstanceProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['processDefinitionKey', 'processDefinitionId', 'businessKey'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters(['processDefinition' => 'processDefinitionId']),
                $this->listQuery(self::FILTERS, 'startTime'),
            );
            $envelope = $client->listProcessInstances($query);

            return $this->paginate($envelope, FlowProcessInstance::fromApi(...));
        }

        $data = $client->findProcessInstance((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowProcessInstance::fromApi($data) : null;
    }
}
