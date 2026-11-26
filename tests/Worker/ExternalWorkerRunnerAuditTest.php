<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:48:37 UTC

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
 * Audit entries of the engine writes of {@see ExternalWorkerRunner}: via
 * "worker", the worker id as actor, attempt then success or failure. The
 * runner is driven against a client double through its resolveClient()
 * seam; the locator is never used.
 */
final class ExternalWorkerRunnerAuditTest extends TestCase
{
    private const string WORKER = 'worker-1';

    private const string TOPIC = 'audit-topic';

    private RecordingLogger $log;

    protected function setUp(): void
    {
        $this->log = new RecordingLogger();
    }

    /**
     * @return iterable<string, array{ExternalWorkerOutcome, string, string, array<string, mixed>}>
     */
    public static function outcomes(): iterable
    {
        yield 'complete' => [ExternalWorkerOutcome::complete(['a' => 1]), 'completeExternalWorkerJob', 'external_worker.complete', []];
        yield 'fail' => [ExternalWorkerOutcome::fail(message: 'broken'), 'failExternalWorkerJob', 'external_worker.fail', ['error' => 'broken']];
        yield 'bpmn error' => [ExternalWorkerOutcome::bpmnError('E1'), 'bpmnErrorExternalWorkerJob', 'external_worker.bpmn_error', ['errorCode' => 'E1']];
    }

    /**
     * @param array<string, mixed> $extra
     */
    #[DataProvider('outcomes')]
    public function testReportedOutcomeRecordsAttemptAndSuccess(ExternalWorkerOutcome $outcome, string $method, string $action, array $extra): void
    {
        $client = $this->client();
        $client->expects($this->once())->method($method);

        $this->runner($client, $outcome)->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);

        $context = ['via' => 'worker', 'actor' => self::WORKER, 'topic' => self::TOPIC, 'job' => 'job-1', 'worker' => self::WORKER, 'processInstance' => 'pi-1', ...$extra];
        self::assertSame([
            ['level' => 'info', 'message' => 'flowable.' . $action . '.attempt', 'context' => ['state' => 'attempt', ...$context]],
            ['level' => 'info', 'message' => 'flowable.' . $action, 'context' => ['state' => 'success', ...$context]],
        ], $this->auditRecords());
    }

    public function testFailedReportRecordsAttemptAndFailureAndIsRethrown(): void
    {
        $error = FlowableApiException::unreachable();
        $client = $this->client();
        $client->expects($this->once())->method('completeExternalWorkerJob')->willThrowException($error);

        try {
            $this->runner($client, ExternalWorkerOutcome::complete())->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);
            self::fail('The engine error was not rethrown.');
        } catch (FlowableApiException $e) {
            self::assertSame($error, $e);
        }

        $records = $this->auditRecords();
        self::assertSame(['flowable.external_worker.complete.attempt', 'flowable.external_worker.complete.failure'], array_column($records, 'message'));
        self::assertSame('warning', $records[1]['level']);
        self::assertSame('worker', $records[1]['context']['via']);
        self::assertSame(self::WORKER, $records[1]['context']['actor']);
        self::assertSame(504, $records[1]['context']['status']);
        self::assertSame($error->getCorrelationId(), $records[1]['context']['correlationId']);
    }

    public function testUnhandledTopicIsUnacquiredWithAuditAndKeepsTheWarning(): void
    {
        $client = $this->client();
        $client->expects($this->once())->method('unacquireExternalWorkerJob');

        $this->runner($client, null)->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);

        $context = ['via' => 'worker', 'actor' => self::WORKER, 'topic' => self::TOPIC, 'job' => 'job-1', 'worker' => self::WORKER, 'reason' => 'unhandled_topic'];
        self::assertSame([
            ['state' => 'attempt', ...$context],
            ['state' => 'success', ...$context],
        ], array_column($this->auditRecords(), 'context'));
        self::assertSame(
            ['flowable.external_worker.start', 'flowable.external_worker.unacquire.attempt', 'flowable.external_worker.unacquire', 'flowable.external_worker.unhandled_topic', 'flowable.external_worker.stop'],
            $this->log->messages(),
        );
    }

    public function testOperationalLinesStayAroundTheAuditEntries(): void
    {
        $client = $this->client();
        $client->expects($this->once())->method('completeExternalWorkerJob');

        $this->runner($client, ExternalWorkerOutcome::complete())->run(self::WORKER, [self::TOPIC], once: true, idleSleepSeconds: 0);

        self::assertSame(
            ['flowable.external_worker.start', 'flowable.external_worker.complete.attempt', 'flowable.external_worker.complete', 'flowable.external_worker.stop'],
            $this->log->messages(),
        );
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject&FlowableClientInterface
     */
    private function client(): FlowableClientInterface
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->method('acquireExternalWorkerJobs')->willReturn([['id' => 'job-1', 'processInstanceId' => 'pi-1']]);

        return $client;
    }

    /**
     * A runner with one handler for {@see self::TOPIC} answering $outcome, or
     * none when $outcome is null.
     */
    private function runner(FlowableClientInterface $client, ?ExternalWorkerOutcome $outcome): ExternalWorkerRunner
    {
        $handlers = $outcome === null ? [] : [new class($outcome) implements ExternalWorkerHandlerInterface {
            public function __construct(private readonly ExternalWorkerOutcome $outcome)
            {
            }

            public function topics(): array
            {
                return [ExternalWorkerRunnerAuditTest::topic()];
            }

            public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome
            {
                return $this->outcome;
            }
        }];

        // FlowableClientLocator is final and needs a database; resolveClient()
        // is overridden, so an uninitialised instance is never touched.
        $locator = (new \ReflectionClass(FlowableClientLocator::class))->newInstanceWithoutConstructor();

        return new class($locator, new ExternalWorkerHandlerRegistry($handlers), new FlowableVariableMapper(), $this->log, $client) extends ExternalWorkerRunner {
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

    public static function topic(): string
    {
        return self::TOPIC;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    private function auditRecords(): array
    {
        return array_values(array_filter(
            $this->log->records(),
            static fn (array $record): bool => isset($record['context']['state'], $record['context']['via']),
        ));
    }
}
