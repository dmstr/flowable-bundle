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
 * Activities and failed decision executions are fetched newest first and
 * returned oldest first: when an instance has more than COMPOSITE_LIMIT of
 * them, the oldest are cut, not the newest ones someone debugging a stuck or
 * failed instance needs. The *Total fields are the engine's totals, except
 * when the engine ignores the failed=true filter and also returns successful
 * executions: those are dropped and failedDecisionExecutionsTotal is then the
 * number of failed rows within the fetched page.
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

        [$activities, $activitiesTotal] = self::newestRowsAscending(
            $client->listHistoricActivities(['processInstanceId' => $id] + self::newestPage()),
            FlowHistoricActivity::fromApi(...),
        );
        [$variables, $variablesTotal] = self::rows(
            $client->listHistoricVariables(['processInstanceId' => $id, 'start' => 0, 'size' => self::COMPOSITE_LIMIT]),
            FlowHistoricVariable::fromApi(...),
        );
        [$decisions, $decisionsTotal] = self::newestRowsAscending(
            $client->listHistoricDecisionExecutions(['instanceId' => $id, 'failed' => 'true'] + self::newestPage()),
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

    /**
     * Query page for the newest COMPOSITE_LIMIT rows by start time.
     *
     * @return array{start:int,size:int,sort:string,order:string}
     */
    private static function newestPage(): array
    {
        return ['start' => 0, 'size' => self::COMPOSITE_LIMIT, 'sort' => 'startTime', 'order' => 'desc'];
    }

    /**
     * The rows of a page fetched with newestPage(), turned back into
     * ascending order (oldest first). Rows with the same start time come in
     * the reverse of the engine's descending order.
     *
     * @param array<string,mixed> $envelope Flowable list envelope
     * @param callable(array<string,mixed>):object $fromApi
     * @return array{0:list<array<string,mixed>>,1:int} [rows, total]
     */
    private static function newestRowsAscending(array $envelope, callable $fromApi): array
    {
        [$rows, $total] = self::rows($envelope, $fromApi);

        return [array_reverse($rows), $total];
    }
}
// - revised 2026-11-26 (MCP tool arguments validated against the published input schema)
// - revised 2026-11-26 (per-provider MCP input validation removed, done centrally)
// - revised 2026-11-26 (newest 200 activities and failed decision executions, returned oldest first)
