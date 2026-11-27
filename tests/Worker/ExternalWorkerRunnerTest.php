<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Worker;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Tests\Kernel\RecordingLogger;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerRegistry;
use Dmstr\Flowable\Worker\ExternalWorkerJob;
use Dmstr\Flowable\Worker\ExternalWorkerOutcome;
use Dmstr\Flowable\Worker\ExternalWorkerRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Poll loop behaviour of {@see ExternalWorkerRunner}: what is reported, what
 * is counted and, above all, that every acquired job is either reported or
 * released when the loop ends — on a failed report, a stop request or a
 * wiring error. The runner is driven against a recording client stub through
 * its resolveClient() seam; the locator is never used.
 */
final class ExternalWorkerRunnerTest extends TestCase
{
    private const string WORKER = 'worker-1';

    private const string TOPIC = 'topic-a';

    private RecordingLogger $log;

    /** @var list<array{method: string, id: ?string, payload: array<string, mixed>}> */
    private array $calls = [];

    /** @var list<list<array<string, mixed>>> batches answered by successive acquire calls; [] once used up */
    private array $batches = [];

    /** @var array<string, \Throwable> "<method>:<job id>" => exception thrown on every such call */
    private array $failures = [];

    private ?ExternalWorkerRunner $runner = null;

    protected function setUp(): void
    {
        $this->log = new RecordingLogger();
    }

    protected function tearDown(): void
    {
        self::assertNotContains('unacquireAllExternalWorkerJobs', array_column($this->calls, 'method'), 'The runner must never release all jobs of a worker id.');
    }

    // --- Regression: the acquired batch is released when the loop ends ---

    public function testFailedReportReleasesTheWholeBatchIncludingTheFailedJob(): void
    {
        $this->batches = [$this->jobs(3)];
        $error = FlowableApiException::unreachable();
        $this->failures['completeExternalWorkerJob:job-1'] = $error;
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        try {
            $runner->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);
            self::fail('The engine error was not rethrown.');
        } catch (FlowableApiException $e) {
            self::assertSame($error, $e);
        }

        self::assertSame([
            'acquireExternalWorkerJobs',
            'completeExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-2',
            'unacquireExternalWorkerJob:job-3',
        ], $this->callSummary());
        self::assertSame([
            'flowable.external_worker.start',
            'flowable.external_worker.complete.attempt',
            'flowable.external_worker.complete.failure',
            'flowable.external_worker.unacquire.attempt',
            'flowable.external_worker.unacquire',
            'flowable.external_worker.unacquire.attempt',
            'flowable.external_worker.unacquire',
            'flowable.external_worker.unacquire.attempt',
            'flowable.external_worker.unacquire',
            'flowable.external_worker.stop',
        ], $this->log->messages());
        $this->assertReleasedAtShutdown(['job-1', 'job-2', 'job-3']);
        self::assertSame([], $runner->heldJobIds());
    }

    public function testStopRequestedByTheHandlerReleasesTheRestOfTheBatch(): void
    {
        $this->batches = [$this->jobs(3)];
        $runner = $this->runner([self::TOPIC => function (ExternalWorkerJob $job): ExternalWorkerOutcome {
            $this->runner?->requestStop();

            return ExternalWorkerOutcome::complete();
        }]);

        $processed = $runner->run(self::WORKER, [self::TOPIC], idleSleepSeconds: 0);

        self::assertSame(1, $processed);
        self::assertSame([
            'acquireExternalWorkerJobs',
            'completeExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-2',
            'unacquireExternalWorkerJob:job-3',
        ], $this->callSummary());
        $this->assertReleasedAtShutdown(['job-2', 'job-3']);
        self::assertTrue($this->log->recordsWithMessage('flowable.external_worker.stop')[0]['context']['stopRequested']);
        self::assertSame(1, $this->log->recordsWithMessage('flowable.external_worker.stop')[0]['context']['processed']);
        self::assertSame([], $runner->heldJobIds());
    }

    public function testTopicClaimedByTwoHandlersReleasesTheAcquiredBatch(): void
    {
        $this->batches = [$this->jobs(2)];
        $runner = $this->runnerWithHandlers([
            $this->handler([self::TOPIC], $this->completes()),
            $this->handler([self::TOPIC], $this->completes()),
        ]);

        try {
            $runner->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);
            self::fail('The doubly claimed topic was not reported.');
        } catch (\LogicException $e) {
            self::assertStringContainsString('"topic-a" is claimed by two handlers', $e->getMessage());
        }

        self::assertSame([
            'acquireExternalWorkerJobs',
            'unacquireExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-2',
        ], $this->callSummary());
        $this->assertReleasedAtShutdown(['job-1', 'job-2']);
        self::assertSame([], $runner->heldJobIds());
    }

    public function testFailedUnacquireOfAnUnhandledTopicReleasesTheRestAndKeepsTheOriginalError(): void
    {
        $this->batches = [$this->jobs(2)];
        $error = FlowableApiException::fromUpstreamStatus(500);
        $this->failures['unacquireExternalWorkerJob:job-1'] = $error;
        $runner = $this->runner([]);

        try {
            $runner->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);
            self::fail('The engine error was not rethrown.');
        } catch (FlowableApiException $e) {
            self::assertSame($error, $e);
        }

        self::assertSame([
            'acquireExternalWorkerJobs',
            'unacquireExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-2',
        ], $this->callSummary());
        $failed = $this->log->recordsWithMessage('flowable.external_worker.unacquire_failed');
        self::assertCount(1, $failed);
        self::assertSame('job-1', $failed[0]['context']['job']);
        $this->assertReleasedAtShutdown(['job-2']);
        self::assertSame([], $runner->heldJobIds());
    }

    // --- Held job ids ---

    public function testNoJobIsHeldAfterASuccessfulRun(): void
    {
        $this->batches = [$this->jobs(3)];
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        self::assertSame(3, $runner->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0));

        self::assertSame([], $runner->heldJobIds());
        self::assertSame([], $this->log->recordsWithMessage('flowable.external_worker.unacquire'));
    }

    // --- Reports and their payloads ---

    public function testThrowingHandlerBecomesAFailReportWithoutARetriesOverride(): void
    {
        $this->batches = [$this->jobs(1)];
        $runner = $this->runner([self::TOPIC => static fn (): ExternalWorkerOutcome => throw new \RuntimeException('handler broke')]);

        self::assertSame(1, $runner->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0));

        self::assertSame(['acquireExternalWorkerJobs', 'failExternalWorkerJob:job-1'], $this->callSummary());
        $payload = $this->calls[1]['payload'];
        self::assertSame(['workerId', 'errorMessage', 'errorDetails'], array_keys($payload));
        self::assertSame(self::WORKER, $payload['workerId']);
        self::assertSame('handler broke', $payload['errorMessage']);
        self::assertIsString($payload['errorDetails']);

        $threw = $this->log->recordsWithMessage('flowable.external_worker.handler_threw');
        self::assertCount(1, $threw);
        self::assertSame('error', $threw[0]['level']);
        self::assertSame(\RuntimeException::class, $threw[0]['context']['exception']);
        self::assertSame('handler broke', $threw[0]['context']['message']);
        self::assertSame('job-1', $threw[0]['context']['job']);
        self::assertSame(self::TOPIC, $threw[0]['context']['topic']);
        self::assertSame('handler broke', $this->log->recordsWithMessage('flowable.external_worker.fail')[0]['context']['error']);
    }

    public function testFailOutcomeSendsOnlyWhatTheHandlerDecidedAndTruncatesTheDetails(): void
    {
        $this->batches = [$this->jobs(2)];
        $outcomes = [
            'job-1' => ExternalWorkerOutcome::fail(),
            'job-2' => ExternalWorkerOutcome::fail(retries: 2, retryTimeout: 'PT1M', message: 'broken', details: str_repeat('x', 5000)),
        ];
        $runner = $this->runner([self::TOPIC => static fn (ExternalWorkerJob $job): ExternalWorkerOutcome => $outcomes[$job->id]]);

        $runner->run(self::WORKER, [self::TOPIC], batchSize: 2, once: true, idleSleepSeconds: 0);

        self::assertSame(['workerId' => self::WORKER], $this->calls[1]['payload']);
        self::assertSame([
            'workerId' => self::WORKER,
            'retries' => 2,
            'retryTimeout' => 'PT1M',
            'errorMessage' => 'broken',
            'errorDetails' => str_repeat('x', 4000).' […truncated]',
        ], $this->calls[2]['payload']);
    }

    public function testCompleteAndBpmnErrorSendTheMappedVariables(): void
    {
        $this->batches = [$this->jobs(2)];
        $outcomes = [
            'job-1' => ExternalWorkerOutcome::complete(['a' => 1, 'b' => 'x']),
            'job-2' => ExternalWorkerOutcome::bpmnError('E1', ['c' => true]),
        ];
        $runner = $this->runner([self::TOPIC => static fn (ExternalWorkerJob $job): ExternalWorkerOutcome => $outcomes[$job->id]]);

        self::assertSame(2, $runner->run(self::WORKER, [self::TOPIC], batchSize: 2, once: true, idleSleepSeconds: 0));

        self::assertSame(['acquireExternalWorkerJobs', 'completeExternalWorkerJob:job-1', 'bpmnErrorExternalWorkerJob:job-2'], $this->callSummary());
        self::assertSame([
            'workerId' => self::WORKER,
            'variables' => [
                ['name' => 'a', 'value' => 1, 'type' => 'integer'],
                ['name' => 'b', 'value' => 'x', 'type' => 'string'],
            ],
        ], $this->calls[1]['payload']);
        self::assertSame([
            'workerId' => self::WORKER,
            'errorCode' => 'E1',
            'variables' => [['name' => 'c', 'value' => true, 'type' => 'boolean']],
        ], $this->calls[2]['payload']);
    }

    // --- Counting ---

    public function testUnhandledTopicIsUnacquiredAndNotCounted(): void
    {
        $this->batches = [$this->jobs(2)];
        $runner = $this->runner([]);

        self::assertSame(0, $runner->run(self::WORKER, [self::TOPIC], batchSize: 2, once: true, idleSleepSeconds: 0));

        self::assertSame([
            'acquireExternalWorkerJobs',
            'unacquireExternalWorkerJob:job-1',
            'unacquireExternalWorkerJob:job-2',
        ], $this->callSummary());
        self::assertSame(['unhandled_topic', 'unhandled_topic'], array_map(
            static fn (array $record): string => $record['context']['reason'],
            $this->log->recordsWithMessage('flowable.external_worker.unacquire'),
        ));
        self::assertCount(2, $this->log->recordsWithMessage('flowable.external_worker.unhandled_topic'));
        self::assertSame(0, $this->log->recordsWithMessage('flowable.external_worker.stop')[0]['context']['processed']);
        self::assertSame([], $runner->heldJobIds());
    }

    // --- Batches ---

    /**
     * @return iterable<string, array{int}>
     */
    public static function batchSizes(): iterable
    {
        yield 'batch of 1' => [1];
        yield 'batch of 3' => [3];
    }

    #[DataProvider('batchSizes')]
    public function testAcquireRequestShapeAndBatchHandling(int $batchSize): void
    {
        $this->batches = [$this->jobs($batchSize)];
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        $processed = $runner->run(self::WORKER, [self::TOPIC, self::TOPIC], lockDuration: 'PT2M', batchSize: $batchSize, once: true, idleSleepSeconds: 0);

        self::assertSame($batchSize, $processed);
        self::assertSame([
            'topic' => self::TOPIC,
            'workerId' => self::WORKER,
            'lockDuration' => 'PT2M',
            'numberOfTasks' => $batchSize,
        ], $this->calls[0]['payload']);
        self::assertSame(1, array_count_values(array_column($this->calls, 'method'))['acquireExternalWorkerJobs'], 'Duplicate topics are polled once.');
        self::assertCount($batchSize, $this->log->recordsWithMessage('flowable.external_worker.complete'));
        self::assertSame([], $runner->heldJobIds());
    }

    public function testDefaultAcquireRequest(): void
    {
        $this->runner([self::TOPIC => $this->completes()])->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);

        self::assertSame([
            'topic' => self::TOPIC,
            'workerId' => self::WORKER,
            'lockDuration' => 'PT10M',
            'numberOfTasks' => 1,
        ], $this->calls[0]['payload']);
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function failurePositions(): iterable
    {
        yield 'first' => ['job-1', [], ['job-1', 'job-2', 'job-3']];
        yield 'middle' => ['job-2', ['job-1'], ['job-2', 'job-3']];
        yield 'last' => ['job-3', ['job-1', 'job-2'], ['job-3']];
    }

    /**
     * @param list<string> $reported
     * @param list<string> $released
     */
    #[DataProvider('failurePositions')]
    public function testFailedReportAtAnyPositionReleasesItAndEverythingAfterIt(string $failing, array $reported, array $released): void
    {
        $this->batches = [$this->jobs(3)];
        $this->failures['completeExternalWorkerJob:'.$failing] = FlowableApiException::fromUpstreamStatus(503);
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        try {
            $runner->run(self::WORKER, [self::TOPIC], batchSize: 3, once: true, idleSleepSeconds: 0);
            self::fail('The engine error was not rethrown.');
        } catch (FlowableApiException) {
        }

        self::assertSame($reported, array_column(array_column($this->log->recordsWithMessage('flowable.external_worker.complete'), 'context'), 'job'));
        $this->assertReleasedAtShutdown($released);
        self::assertSame([], $runner->heldJobIds());
    }

    public function testStopAfterTheLastJobLeavesNothingToRelease(): void
    {
        $this->batches = [$this->jobs(3)];
        $runner = $this->runner([self::TOPIC => function (ExternalWorkerJob $job): ExternalWorkerOutcome {
            if ($job->id === 'job-3') {
                $this->runner?->requestStop();
            }

            return ExternalWorkerOutcome::complete();
        }]);

        self::assertSame(3, $runner->run(self::WORKER, [self::TOPIC], batchSize: 3, idleSleepSeconds: 0));

        self::assertNotContains('unacquireExternalWorkerJob', array_column($this->calls, 'method'));
        self::assertSame(1, array_count_values(array_column($this->calls, 'method'))['acquireExternalWorkerJobs']);
        self::assertSame([], $runner->heldJobIds());
    }

    public function testStopRequestSkipsTheRemainingTopics(): void
    {
        $this->batches = [$this->jobs(1)];
        $runner = $this->runner([
            self::TOPIC => function (): ExternalWorkerOutcome {
                $this->runner?->requestStop();

                return ExternalWorkerOutcome::complete();
            },
            'topic-b' => $this->completes(),
        ]);

        self::assertSame(1, $runner->run(self::WORKER, [self::TOPIC, 'topic-b'], idleSleepSeconds: 0));

        self::assertSame(['acquireExternalWorkerJobs', 'completeExternalWorkerJob:job-1'], $this->callSummary());
    }

    public function testJobWithoutIdIsSkipped(): void
    {
        $this->batches = [[['id' => '', 'processInstanceId' => 'pi-0'], ['processInstanceId' => 'pi-x'], ['id' => 'job-1', 'processInstanceId' => 'pi-1']]];
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        self::assertSame(1, $runner->run(self::WORKER, [self::TOPIC], batchSize: 3, once: true, idleSleepSeconds: 0));

        self::assertSame(['acquireExternalWorkerJobs', 'completeExternalWorkerJob:job-1'], $this->callSummary());
        self::assertSame([], $runner->heldJobIds());
    }

    public function testFailingReleaseAtShutdownIsLoggedAndDoesNotMaskTheOriginalError(): void
    {
        $this->batches = [$this->jobs(3)];
        $error = FlowableApiException::unreachable();
        $this->failures['completeExternalWorkerJob:job-1'] = $error;
        $this->failures['unacquireExternalWorkerJob:job-2'] = new \RuntimeException('release broke');
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        try {
            $runner->run(self::WORKER, [self::TOPIC], batchSize: 3, once: true, idleSleepSeconds: 0);
            self::fail('The engine error was not rethrown.');
        } catch (FlowableApiException $e) {
            self::assertSame($error, $e);
        }

        $failed = $this->log->recordsWithMessage('flowable.external_worker.unacquire_failed');
        self::assertCount(1, $failed);
        self::assertSame(['job' => 'job-2', 'worker' => self::WORKER, 'message' => 'release broke'], $failed[0]['context']);
        self::assertSame(
            ['job-1', 'job-2', 'job-3'],
            array_column(array_filter($this->calls, static fn (array $call): bool => $call['method'] === 'unacquireExternalWorkerJob'), 'id'),
        );
        $this->assertReleasedAtShutdown(['job-1', 'job-3']);
        self::assertSame([], $runner->heldJobIds());
    }

    // --- Limits ---

    public function testMaxJobsCapsTheAcquiredBatch(): void
    {
        $this->batches = [$this->jobs(2), [['id' => 'job-3', 'processInstanceId' => 'pi-3']]];
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        self::assertSame(3, $runner->run(self::WORKER, [self::TOPIC], batchSize: 2, maxJobs: 3, idleSleepSeconds: 0));

        $acquires = array_values(array_filter($this->calls, static fn (array $call): bool => $call['method'] === 'acquireExternalWorkerJobs'));
        self::assertSame([2, 1], array_map(static fn (array $call): int => $call['payload']['numberOfTasks'], $acquires));
        self::assertSame([], $runner->heldJobIds());
    }

    public function testExpiredTimeLimitAcquiresNothing(): void
    {
        $this->batches = [$this->jobs(1)];
        $runner = $this->runner([self::TOPIC => $this->completes()]);

        self::assertSame(0, $runner->run(self::WORKER, [self::TOPIC], timeLimit: 0, idleSleepSeconds: 0));

        self::assertSame([], $this->calls);
        self::assertSame(['flowable.external_worker.start', 'flowable.external_worker.stop'], $this->log->messages());
    }

    public function testNoTopicsAtAllReturnsWithoutPolling(): void
    {
        self::assertSame(0, $this->runner([])->run(self::WORKER, idleSleepSeconds: 0));

        self::assertSame([], $this->calls);
        self::assertSame(['flowable.external_worker.no_topics'], $this->log->messages());
    }

    // --- Helpers ---

    /**
     * Assert that exactly these jobs were released with reason "shutdown",
     * each with a success audit entry, in this order.
     *
     * @param list<string> $jobIds
     */
    private function assertReleasedAtShutdown(array $jobIds): void
    {
        $released = array_values(array_filter(
            $this->log->recordsWithMessage('flowable.external_worker.unacquire'),
            static fn (array $record): bool => $record['context']['reason'] === 'shutdown',
        ));
        self::assertSame($jobIds, array_map(static fn (array $record): string => $record['context']['job'], $released));
        foreach ($released as $record) {
            self::assertSame('success', $record['context']['state']);
            self::assertSame('worker', $record['context']['via']);
            self::assertSame(self::WORKER, $record['context']['actor']);
        }
    }

    /**
     * @return list<string> "<method>" for acquire, "<method>:<job id>" for the rest
     */
    private function callSummary(): array
    {
        return array_map(
            static fn (array $call): string => $call['id'] === null ? $call['method'] : $call['method'].':'.$call['id'],
            $this->calls,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jobs(int $count): array
    {
        $jobs = [];
        for ($i = 1; $i <= $count; ++$i) {
            $jobs[] = ['id' => 'job-'.$i, 'processInstanceId' => 'pi-'.$i];
        }

        return $jobs;
    }

    /**
     * @return \Closure(ExternalWorkerJob): ExternalWorkerOutcome
     */
    private function completes(): \Closure
    {
        return static fn (): ExternalWorkerOutcome => ExternalWorkerOutcome::complete();
    }

    /**
     * @param list<string> $topics
     * @param \Closure(ExternalWorkerJob): ExternalWorkerOutcome $handle
     */
    private function handler(array $topics, \Closure $handle): ExternalWorkerHandlerInterface
    {
        return new class($topics, $handle) implements ExternalWorkerHandlerInterface {
            /**
             * @param list<string> $topics
             * @param \Closure(ExternalWorkerJob): ExternalWorkerOutcome $handle
             */
            public function __construct(private readonly array $topics, private readonly \Closure $handle)
            {
            }

            public function topics(): array
            {
                return $this->topics;
            }

            public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome
            {
                return ($this->handle)($job);
            }
        };
    }

    /**
     * @param array<string, \Closure(ExternalWorkerJob): ExternalWorkerOutcome> $handlers topic => handle
     */
    private function runner(array $handlers): ExternalWorkerRunner
    {
        $list = [];
        foreach ($handlers as $topic => $handle) {
            $list[] = $this->handler([$topic], $handle);
        }

        return $this->runnerWithHandlers($list);
    }

    /**
     * @param list<ExternalWorkerHandlerInterface> $handlers
     */
    private function runnerWithHandlers(array $handlers): ExternalWorkerRunner
    {
        // FlowableClientLocator is final and needs a database; resolveClient()
        // is overridden, so an uninitialised instance is never touched.
        $locator = (new \ReflectionClass(FlowableClientLocator::class))->newInstanceWithoutConstructor();

        return $this->runner = new class($locator, new ExternalWorkerHandlerRegistry($handlers), new FlowableVariableMapper(), $this->log, $this->client()) extends ExternalWorkerRunner {
            public function __construct(
                FlowableClientLocator $locator,
                ExternalWorkerHandlerRegistry $registry,
                FlowableVariableMapper $variableMapper,
                RecordingLogger $flowableLogger,
                private readonly FlowableClientInterface $client,
            ) {
                parent::__construct($locator, $registry, $variableMapper, $flowableLogger);
            }

            protected function resolveClient(?string $apiConfiguration): FlowableClientInterface
            {
                return $this->client;
            }
        };
    }

    /**
     * A client stub that records every worker call and answers acquire from
     * {@see self::$batches}, throwing what {@see self::$failures} says.
     */
    private function client(): FlowableClientInterface
    {
        $client = $this->createStub(FlowableClientInterface::class);
        $client->method('acquireExternalWorkerJobs')->willReturnCallback(function (array $payload): array {
            $this->calls[] = ['method' => 'acquireExternalWorkerJobs', 'id' => null, 'payload' => $payload];

            return array_shift($this->batches) ?? [];
        });
        foreach (['completeExternalWorkerJob', 'failExternalWorkerJob', 'bpmnErrorExternalWorkerJob', 'unacquireExternalWorkerJob'] as $method) {
            $client->method($method)->willReturnCallback(function (string $id, array $payload) use ($method): void {
                $this->calls[] = ['method' => $method, 'id' => $id, 'payload' => $payload];
                if (isset($this->failures[$method.':'.$id])) {
                    throw $this->failures[$method.':'.$id];
                }
            });
        }
        $client->method('unacquireAllExternalWorkerJobs')->willReturnCallback(function (array $payload): void {
            $this->calls[] = ['method' => 'unacquireAllExternalWorkerJobs', 'id' => null, 'payload' => $payload];
        });

        return $client;
    }
}
