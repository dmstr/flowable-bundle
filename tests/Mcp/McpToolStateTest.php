<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Mcp;

use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use Dmstr\Flowable\ApiResource\FlowDeployment;
use Dmstr\Flowable\ApiResource\Output\FlowProcessHistory;
use Dmstr\Flowable\ApiResource\Output\FlowProcessStatus;
use Dmstr\Flowable\ApiResource\Output\FlowTaskForm;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Service\TaskInputSchema;
use Dmstr\Flowable\State\DeploymentBundleProcessor;
use Dmstr\Flowable\State\FlowExternalWorkerJobProvider;
use Dmstr\Flowable\State\FlowJobProvider;
use Dmstr\Flowable\State\FlowProcessHistoryProvider;
use Dmstr\Flowable\State\FlowProcessStatusProvider;
use Dmstr\Flowable\State\FlowTaskFormProvider;
use Dmstr\Flowable\State\TimerJobExecuteProcessor;
use Dmstr\Flowable\Tests\State\StateTestCase;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Providers and processors behind the new MCP tools, against a client double.
 *
 * Providers get the real McpTool of their tool (StateTestCase::mcpTool())
 * with arguments its published input schema declares. Over the MCP transport
 * the arguments are validated before the provider runs
 * (State\McpToolInputValidationProvider); rejected arguments are tested there
 * (Tests\Http\UploadAndReadToolInputTest), not here.
 */
final class McpToolStateTest extends StateTestCase
{
    private const EMPTY_LIST = ['data' => [], 'total' => 0, 'start' => 0, 'size' => 200];

    // --- (b) composite tools: a failing sub-call fails the tool ---------------

    public function testProcessStatusCombinesInstanceTasksAndExecutions(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findProcessInstance')->with('pi-1')->willReturn(['id' => 'pi-1', 'processDefinitionKey' => 'order', 'businessKey' => 'B-1']);
        $client->expects($this->once())->method('listTasks')
            ->with(['processInstanceId' => 'pi-1', 'start' => 0, 'size' => 200, 'sort' => 'createTime', 'order' => 'asc'])
            ->willReturn(['data' => [['id' => 't-1', 'name' => 'Approve', 'processInstanceId' => 'pi-1', 'taskDefinitionKey' => 'approve']], 'total' => 1]);
        $client->expects($this->once())->method('listExecutions')
            ->with(['processInstanceId' => 'pi-1', 'start' => 0, 'size' => 200])
            ->willReturn(['data' => [['id' => 'pi-1', 'activityId' => null], ['id' => 'ex-2', 'activityId' => 'approve'], ['id' => 'ex-3', 'activityId' => 'waitForPayment']]]);

        $status = $this->statusProvider($client)->provide(self::mcpTool('flowable_get_process_status'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));

        self::assertInstanceOf(FlowProcessStatus::class, $status);
        self::assertSame('pi-1', $status->processInstance['id']);
        self::assertSame('B-1', $status->processInstance['businessKey']);
        self::assertArrayNotHasKey('raw', $status->processInstance);
        self::assertArrayNotHasKey('processDefinition', $status->processInstance);
        self::assertSame('t-1', $status->openTasks[0]['id']);
        self::assertArrayNotHasKey('processInstance', $status->openTasks[0]);
        self::assertSame(1, $status->openTasksTotal);
        self::assertSame(['approve', 'waitForPayment'], $status->currentActivityIds);
        self::assertSame([['id' => 'ex-2', 'activityId' => 'approve'], ['id' => 'ex-3', 'activityId' => 'waitForPayment']], $status->activeExecutions);
    }

    public function testProcessStatusFailsWhenASubCallFails(): void
    {
        $client = $this->createStub(FlowableClientInterface::class);
        $client->method('findProcessInstance')->willReturn(['id' => 'pi-1']);
        $client->method('listTasks')->willReturn(self::EMPTY_LIST);
        $client->method('listExecutions')->willThrowException(FlowableApiException::fromUpstreamStatus(500, 'boom'));

        $this->expectException(FlowableApiException::class);
        $this->statusProvider($client)->provide(self::mcpTool('flowable_get_process_status'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));
    }

    public function testProcessStatusOfAnEndedInstanceIsNotFound(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findProcessInstance')->willReturn(null);
        $client->expects($this->never())->method('listTasks');

        $this->expectException(NotFoundHttpException::class);
        $this->statusProvider($client)->provide(self::mcpTool('flowable_get_process_status'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));
    }

    public function testHistoryCombinesActivitiesVariablesAndFailedDecisions(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findHistoricProcessInstance')->with('pi-1')->willReturn(['id' => 'pi-1']);
        $client->expects($this->once())->method('listHistoricActivities')
            ->with(['processInstanceId' => 'pi-1', 'start' => 0, 'size' => 200, 'sort' => 'startTime', 'order' => 'desc'])
            ->willReturn(['data' => [['id' => 'a-1', 'activityId' => 'start', 'activityType' => 'startEvent']], 'total' => 1]);
        $client->expects($this->once())->method('listHistoricVariables')
            ->with(['processInstanceId' => 'pi-1', 'start' => 0, 'size' => 200])
            ->willReturn(['data' => [['id' => 'v-1', 'variable' => ['name' => 'amount', 'type' => 'integer', 'value' => 5]]], 'total' => 1]);
        $client->expects($this->once())->method('listHistoricDecisionExecutions')
            ->with(['instanceId' => 'pi-1', 'failed' => 'true', 'start' => 0, 'size' => 200, 'sort' => 'startTime', 'order' => 'desc'])
            ->willReturn(['data' => [['id' => 'd-1', 'decisionKey' => 'rate', 'failed' => true], ['id' => 'd-2', 'decisionKey' => 'rate', 'failed' => false]], 'total' => 2]);

        $history = $this->historyProvider($client)->provide(self::mcpTool('flowable_history_get'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));

        self::assertInstanceOf(FlowProcessHistory::class, $history);
        self::assertSame('pi-1', $history->processInstanceId);
        self::assertSame('start', $history->activities[0]['activityId']);
        self::assertSame(1, $history->activitiesTotal);
        self::assertSame(1, $history->variablesTotal);
        self::assertArrayNotHasKey('raw', $history->variables[0]);
        // An engine that ignores the failed filter cannot leak successful runs.
        self::assertSame(['d-1'], array_column($history->failedDecisionExecutions, 'id'));
        self::assertSame(1, $history->failedDecisionExecutionsTotal);
    }

    /**
     * The cap cuts off the oldest rows: the provider asks for the newest page
     * and returns it oldest first, with the engine's total.
     */
    public function testHistoryReturnsTheNewestActivitiesAndFailedDecisionsOldestFirst(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findHistoricProcessInstance')->willReturn(['id' => 'pi-1']);
        $client->expects($this->once())->method('listHistoricActivities')
            ->with(['processInstanceId' => 'pi-1', 'start' => 0, 'size' => 200, 'sort' => 'startTime', 'order' => 'desc'])
            ->willReturn(['data' => [
                ['id' => 'a-450', 'startTime' => '2026-01-01T00:07:30Z'],
                ['id' => 'a-449', 'startTime' => '2026-01-01T00:07:29Z'],
                ['id' => 'a-448', 'startTime' => '2026-01-01T00:07:28Z'],
            ], 'total' => 450]);
        $client->method('listHistoricVariables')->willReturn(self::EMPTY_LIST);
        $client->expects($this->once())->method('listHistoricDecisionExecutions')
            ->with(['instanceId' => 'pi-1', 'failed' => 'true', 'start' => 0, 'size' => 200, 'sort' => 'startTime', 'order' => 'desc'])
            ->willReturn(['data' => [
                ['id' => 'd-9', 'failed' => true, 'startTime' => '2026-01-01T00:09:00Z'],
                ['id' => 'd-8', 'failed' => true, 'startTime' => '2026-01-01T00:08:00Z'],
            ], 'total' => 2]);

        $history = $this->history($client);

        self::assertSame(['a-448', 'a-449', 'a-450'], array_column($history->activities, 'id'));
        self::assertSame(450, $history->activitiesTotal);
        self::assertSame(['d-8', 'd-9'], array_column($history->failedDecisionExecutions, 'id'));
    }

    /**
     * failedDecisionExecutionsTotal, case (a): the engine honours the
     * failed=true filter (only failed rows), so its total is passed through,
     * also when it is larger than the page.
     */
    public function testHistoryDecisionTotalIsTheEngineTotalWhenTheFilterIsHonoured(): void
    {
        $client = $this->historyClient(['data' => [
            ['id' => 'd-2', 'failed' => true],
            ['id' => 'd-1', 'failed' => true],
        ], 'total' => 350]);

        $history = $this->history($client);

        self::assertSame(['d-1', 'd-2'], array_column($history->failedDecisionExecutions, 'id'));
        self::assertSame(350, $history->failedDecisionExecutionsTotal);
    }

    /**
     * failedDecisionExecutionsTotal, case (b): the engine ignores the filter
     * and returns successful executions too; they are dropped and the total
     * is the number of failed rows within the fetched page, not the engine's
     * total.
     */
    public function testHistoryDecisionTotalCountsFailedRowsOfThePageWhenTheFilterIsIgnored(): void
    {
        $client = $this->historyClient(['data' => [
            ['id' => 'd-4', 'failed' => false],
            ['id' => 'd-3', 'failed' => true],
            ['id' => 'd-2', 'failed' => false],
            ['id' => 'd-1', 'failed' => true],
        ], 'total' => 999]);

        $history = $this->history($client);

        self::assertSame(['d-1', 'd-3'], array_column($history->failedDecisionExecutions, 'id'));
        self::assertSame(2, $history->failedDecisionExecutionsTotal);
    }

    public function testHistoryFailsWhenASubCallFails(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findHistoricProcessInstance')->willReturn(['id' => 'pi-1']);
        $client->method('listHistoricActivities')->willReturn(self::EMPTY_LIST);
        $client->method('listHistoricVariables')->willThrowException(FlowableApiException::unreachable('timeout'));
        $client->expects($this->never())->method('listHistoricDecisionExecutions');

        $this->expectException(FlowableApiException::class);
        $this->historyProvider($client)->provide(self::mcpTool('flowable_history_get'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));
    }

    public function testHistoryOfAnUnknownInstanceIsNotFound(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('findHistoricProcessInstance')->willReturn(null);
        $client->expects($this->never())->method('listHistoricActivities');

        $this->expectException(NotFoundHttpException::class);
        $this->historyProvider($client)->provide(self::mcpTool('flowable_history_get'), ['id' => 'nope'], self::mcpContext(['id' => 'nope'], ['id']));
    }

    // --- (c) timer tool --------------------------------------------------------

    public function testTimerToolExecutesExactlyOnce(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('executeTimerJob')->with('timer-1');

        $result = $this->processor(TimerJobExecuteProcessor::class, $client)
            ->process(null, self::tool(), ['id' => 'timer-1'], self::mcpContext(['id' => 'timer-1'], ['id']));

        self::assertNull($result);
    }

    public function testTimerToolRejectsUnknownArguments(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('executeTimerJob');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->processor(TimerJobExecuteProcessor::class, $client)
            ->process(null, self::tool(), ['id' => 'timer-1'], self::mcpContext(['id' => 'timer-1', 'kind' => 'async'], ['id']));
    }

    // --- (d) task form tool ----------------------------------------------------

    public function testTaskFormReturnsWhatTheResolverReturns(): void
    {
        $schema = ['type' => 'object', 'properties' => ['variables' => ['type' => 'object', 'properties' => ['approved' => ['type' => 'boolean']]]]];
        $resolver = $this->createMock(InputSchemaResolverInterface::class);
        $resolver->expects($this->once())->method('resolve')
            ->with('flow_task_complete', ['id' => 'task-1', 'apiConfiguration' => '0a1b2c3d'])
            ->willReturn($schema);
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('findTask');

        $form = $this->formProvider($client, $resolver)
            ->provide(self::mcpTool('flowable_get_task_form'), ['id' => 'task-1'], self::mcpContext(['id' => 'task-1', 'apiConfiguration' => '0a1b2c3d'], ['id']));

        self::assertInstanceOf(FlowTaskForm::class, $form);
        self::assertSame('task-1', $form->taskId);
        self::assertSame($schema, $form->schema);
    }

    public function testTaskFormWithoutFormIsAnOpenSchema(): void
    {
        $resolver = $this->createStub(InputSchemaResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('findTask')->with('task-1')->willReturn(['id' => 'task-1']);

        $form = $this->formProvider($client, $resolver)->provide(self::mcpTool('flowable_get_task_form'), ['id' => 'task-1'], self::mcpContext(['id' => 'task-1'], ['id']));

        self::assertEquals(TaskInputSchema::withoutForm(), $form->schema);
    }

    public function testTaskFormOfAnUnknownTaskIsNotFound(): void
    {
        $resolver = $this->createStub(InputSchemaResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $client = $this->createStub(FlowableClientInterface::class);
        $client->method('findTask')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->formProvider($client, $resolver)->provide(self::mcpTool('flowable_get_task_form'), ['id' => 'nope'], self::mcpContext(['id' => 'nope'], ['id']));
    }

    // --- (e) deploy bundle -----------------------------------------------------

    public function testDeployBundleBuildsOneDeploymentWithAllFiles(): void
    {
        $bpmn = '<?xml version="1.0"?><definitions/>';
        $schema = '{"type":"object"}';
        $captured = [];
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDeployment')
            ->willReturnCallback(function (string $filename, string $content, array $fields) use (&$captured): array {
                $captured = [$filename, $content, $fields];

                return ['id' => 'dep-1', 'name' => 'order'];
            });

        $deployment = $this->processor(DeploymentBundleProcessor::class, $client)->process(null, self::tool(), [], self::mcpContext([
            'deploymentName' => 'order process',
            'category' => 'demo',
            'files' => [
                ['name' => 'order.bpmn20.xml', 'content' => $bpmn],
                ['name' => 'approve.schema.json', 'content' => base64_encode($schema), 'contentEncoding' => 'base64'],
            ],
        ]));

        self::assertInstanceOf(FlowDeployment::class, $deployment);
        self::assertSame('dep-1', $deployment->id);
        [$filename, $archive, $fields] = $captured;
        self::assertSame('order-process.bar', $filename);
        self::assertSame(['deployment-name' => 'order process', 'category' => 'demo'], $fields);

        $entries = self::unzip($archive);
        self::assertSame(['order.bpmn20.xml' => $bpmn, 'approve.schema.json' => $schema], $entries);
    }

    public function testDeployBundleRejectsAnInvalidFileBeforeDeploying(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->processor(DeploymentBundleProcessor::class, $client)->process(null, self::tool(), [], self::mcpContext([
            'deploymentName' => 'x',
            'files' => [
                ['name' => 'order.bpmn20.xml', 'content' => '<definitions/>'],
                ['name' => 'rates.dmn', 'content' => '<definitions/>'],
            ],
        ]));
    }

    // --- (f) external worker job filter, deadletter kind -----------------------

    public function testExternalWorkerJobProcessDefinitionFilterReachesTheClient(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listExternalWorkerJobs')
            ->with($this->callback(static fn (array $query): bool => 'order:3:42' === ($query['processDefinitionId'] ?? null)))
            ->willReturn(self::EMPTY_LIST);

        (new FlowExternalWorkerJobProvider($this->locator($client), $this->requestStack()))
            ->provide(new McpToolCollection(), [], self::mcpContext(['processDefinition' => '/api/flowable/process_definitions/order:3:42']));
    }

    public function testDeadletterToolPinsTheJobKind(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('listJobs');
        $client->expects($this->once())->method('listDeadLetterJobs')->willReturn(self::EMPTY_LIST);

        // A kind argument cannot reach the provider over MCP any more (see
        // Tests\Http\UploadAndReadToolInputTest::invalidReadArguments); the
        // pinned kind still wins over a `kind` query parameter.
        $tool = new McpToolCollection(extraProperties: [FlowJobProvider::FIXED_KIND => 'deadletter']);
        (new FlowJobProvider($this->locator($client), $this->requestStack(Request::create('/', 'GET', ['kind' => 'async']))))
            ->provide($tool, [], []);
    }

    public function testDeadletterToolListsDeadletterJobs(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('listJobs');
        $client->expects($this->once())->method('listDeadLetterJobs')->willReturn(self::EMPTY_LIST);

        (new FlowJobProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_system_list_deadletter_jobs'), [], self::mcpContext([]));
    }

    public function statusProvider(FlowableClientInterface $client): FlowProcessStatusProvider
    {
        return new FlowProcessStatusProvider($this->locator($client), $this->requestStack());
    }


    /**
     * A client whose history is empty except for the given decision executions.
     *
     * @param array<string,mixed> $decisions Flowable list envelope
     */
    private function historyClient(array $decisions): FlowableClientInterface
    {
        $client = $this->createStub(FlowableClientInterface::class);
        $client->method('findHistoricProcessInstance')->willReturn(['id' => 'pi-1']);
        $client->method('listHistoricActivities')->willReturn(self::EMPTY_LIST);
        $client->method('listHistoricVariables')->willReturn(self::EMPTY_LIST);
        $client->method('listHistoricDecisionExecutions')->willReturn($decisions);

        return $client;
    }

    private function history(FlowableClientInterface $client): FlowProcessHistory
    {
        $history = $this->historyProvider($client)->provide(self::mcpTool('flowable_history_get'), ['id' => 'pi-1'], self::mcpContext(['id' => 'pi-1'], ['id']));
        self::assertInstanceOf(FlowProcessHistory::class, $history);

        return $history;
    }
    public function historyProvider(FlowableClientInterface $client): FlowProcessHistoryProvider
    {
        return new FlowProcessHistoryProvider($this->locator($client), $this->requestStack());
    }

    public function formProvider(FlowableClientInterface $client, InputSchemaResolverInterface $resolver): FlowTaskFormProvider
    {
        return new FlowTaskFormProvider($this->locator($client), $this->requestStack(), new TaskInputSchema($resolver));
    }

    /** A bare tool with an id URI variable and no input schema (processors validate the input file). */
    private static function tool(): McpTool
    {
        return new McpTool(uriVariables: ['id' => new Link(identifiers: ['id'])]);
    }

    /**
     * @return array<string,string> entry name => content
     */
    private static function unzip(string $archive): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'flowable-test-');
        try {
            file_put_contents($path, $archive);
            $zip = new \ZipArchive();
            self::assertTrue($zip->open($path));
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $name = (string) $zip->getNameIndex($i);
                $entries[$name] = (string) $zip->getFromIndex($i);
            }
            $zip->close();

            return $entries;
        } finally {
            @unlink($path);
        }
    }
}
// - revised 2026-11-26 (providers called with the real McpTool, read tools validate their arguments)
// - revised 2026-11-26 (argument validation moved to the transport-level and decorator tests)
// - revised 2026-11-26 (history: newest 200 rows oldest first, how the failed decision total comes about)
