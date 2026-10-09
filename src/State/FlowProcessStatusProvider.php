<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowProcessInstance;
use Dmstr\Flowable\ApiResource\FlowTask;
use Dmstr\Flowable\ApiResource\Output\FlowProcessStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * MCP tool flowable_get_process_status (composite): a running process
 * instance, its open user tasks and the BPMN elements its executions wait at
 * (with the execution ids flowable_system_trigger_execution takes), from
 * three engine calls. Any failing call fails the tool; there is no
 * partial result (see CompositeRowsTrait).
 *
 * @implements ProviderInterface<FlowProcessStatus>
 */
final class FlowProcessStatusProvider extends AbstractFlowableProvider implements ProviderInterface
{
    use CompositeRowsTrait;

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): FlowProcessStatus
    {
        $id = self::requiredId($uriVariables, 'process instance id');
        $client = $this->client($context);

        $instance = $client->findProcessInstance($id);
        if (null === $instance) {
            throw new NotFoundHttpException(sprintf(
                'Process instance "%s" is not running (unknown or already ended); flowable_history_get shows ended instances.',
                $id,
            ));
        }

        $page = ['processInstanceId' => $id, 'start' => 0, 'size' => self::COMPOSITE_LIMIT];
        [$tasks, $tasksTotal] = self::rows(
            $client->listTasks($page + ['sort' => 'createTime', 'order' => 'asc']),
            FlowTask::fromApi(...),
        );
        $executions = $client->listExecutions($page);

        // Executions that sit at a flow element are the instance's current
        // wait states; scope executions (the instance itself, sub-process
        // scopes) carry no activityId.
        $activeExecutions = [];
        foreach (\is_array($executions['data'] ?? null) ? $executions['data'] : [] as $execution) {
            $activityId = \is_array($execution) ? ($execution['activityId'] ?? null) : null;
            if (\is_string($activityId) && '' !== $activityId) {
                $activeExecutions[] = ['id' => (string) ($execution['id'] ?? ''), 'activityId' => $activityId];
            }
        }

        $status = new FlowProcessStatus();
        $status->processInstance = self::row(FlowProcessInstance::fromApi($instance));
        $status->openTasks = $tasks;
        $status->openTasksTotal = $tasksTotal;
        $status->currentActivityIds = array_values(array_unique(array_column($activeExecutions, 'activityId')));
        $status->activeExecutions = $activeExecutions;

        return $status;
    }
}
