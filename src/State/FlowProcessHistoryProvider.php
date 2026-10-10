<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowHistoricActivity;
use Dmstr\Flowable\ApiResource\FlowHistoricDecisionExecution;
use Dmstr\Flowable\ApiResource\FlowHistoricVariable;
use Dmstr\Flowable\ApiResource\Output\FlowProcessHistory;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * MCP tool flowable_history_get (composite): historic activities, historic
 * variables and failed decision executions of one process instance, running
 * or ended. The instance is looked up first, so an unknown id is a 404 rather
 * than three empty lists. Any failing call fails the tool; there is no
 * partial result (see CompositeRowsTrait).
 *
 * @implements ProviderInterface<FlowProcessHistory>
 */
final class FlowProcessHistoryProvider extends AbstractFlowableProvider implements ProviderInterface
{
    use CompositeRowsTrait;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FlowProcessHistory
    {
        $id = self::requiredId($uriVariables, 'process instance id');
        $client = $this->client($context);

        if (null === $client->findHistoricProcessInstance($id)) {
            throw new NotFoundHttpException(sprintf('Process instance "%s" not found in the history.', $id));
        }

        $page = ['start' => 0, 'size' => self::COMPOSITE_LIMIT, 'sort' => 'startTime', 'order' => 'asc'];

        [$activities, $activitiesTotal] = self::rows(
            $client->listHistoricActivities(['processInstanceId' => $id] + $page),
            FlowHistoricActivity::fromApi(...),
        );
        [$variables, $variablesTotal] = self::rows(
            $client->listHistoricVariables(['processInstanceId' => $id, 'start' => 0, 'size' => self::COMPOSITE_LIMIT]),
            FlowHistoricVariable::fromApi(...),
        );
        [$decisions, $decisionsTotal] = self::rows(
            $client->listHistoricDecisionExecutions(['instanceId' => $id, 'failed' => 'true'] + $page),
            FlowHistoricDecisionExecution::fromApi(...),
        );
        // Keep only failed executions even if an engine ignores the filter.
        $failed = array_values(array_filter($decisions, static fn (array $row): bool => true === ($row['failed'] ?? false)));
        if (\count($failed) !== \count($decisions)) {
            $decisionsTotal = \count($failed);
        }

        $history = new FlowProcessHistory();
        $history->processInstanceId = $id;
        $history->activities = $activities;
        $history->activitiesTotal = $activitiesTotal;
        $history->variables = $variables;
        $history->variablesTotal = $variablesTotal;
        $history->failedDecisionExecutions = $failed;
        $history->failedDecisionExecutionsTotal = $decisionsTotal;

        return $history;
    }
}
