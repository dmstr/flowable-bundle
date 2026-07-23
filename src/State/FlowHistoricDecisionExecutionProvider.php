<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowHistoricDecisionExecution;

/**
 * @implements ProviderInterface<FlowHistoricDecisionExecution>
 */
final class FlowHistoricDecisionExecutionProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['decisionKey', 'decisionDefinitionId', 'instanceId', 'executionId'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $envelope = $client->listHistoricDecisionExecutions($this->listQuery(self::FILTERS, 'startTime'));

            return $this->paginate($envelope, FlowHistoricDecisionExecution::fromApi(...));
        }

        $data = $client->findHistoricDecisionExecution((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowHistoricDecisionExecution::fromApi($data) : null;
    }
}
