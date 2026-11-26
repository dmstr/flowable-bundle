<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:27:39 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Service;

use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Service\FlowableAuditLogger;
use Dmstr\Flowable\Tests\Kernel\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Pins the shape of the audit entries: messages "flowable.<action>.attempt",
 * "flowable.<action>" (success) and "flowable.<action>.failure", the key
 * order state, via, tool (MCP only), actor, caller context, and on failure
 * status, correlationId (when there is one), exception.
 */
final class FlowableAuditLoggerTest extends TestCase
{
    private RecordingLogger $logger;

    private FlowableAuditLogger $audit;

    protected function setUp(): void
    {
        $this->logger = new RecordingLogger();
        $this->audit = new FlowableAuditLogger($this->logger);
    }

    public function testAuditedWritesAttemptAndSuccessWithTheResultContext(): void
    {
        $result = $this->audit->audited(
            'process_instance.create',
            FlowableAuditLogger::VIA_HTTP,
            'user-1',
            null,
            ['definition' => 'def-1'],
            static fn (): string => 'pi-1',
            static fn (string $id): array => ['instance' => $id],
        );

        self::assertSame('pi-1', $result);
        self::assertSame([
            [
                'level' => LogLevel::INFO,
                'message' => 'flowable.process_instance.create.attempt',
                'context' => ['state' => 'attempt', 'via' => 'http', 'actor' => 'user-1', 'definition' => 'def-1'],
            ],
            [
                'level' => LogLevel::INFO,
                'message' => 'flowable.process_instance.create',
                'context' => ['state' => 'success', 'via' => 'http', 'actor' => 'user-1', 'definition' => 'def-1', 'instance' => 'pi-1'],
            ],
        ], $this->logger->records());
    }

    public function testMcpEntriesCarryTheToolNameAfterVia(): void
    {
        $this->audit->audited('task.complete', FlowableAuditLogger::VIA_MCP, 'user-1', 'flowable_complete_task', ['task' => 't-1'], static fn () => null);

        foreach ($this->logger->records() as $record) {
            self::assertSame(['state', 'via', 'tool', 'actor', 'task'], array_keys($record['context']));
            self::assertSame('mcp', $record['context']['via']);
            self::assertSame('flowable_complete_task', $record['context']['tool']);
        }
        self::assertSame(['flowable.task.complete.attempt', 'flowable.task.complete'], $this->logger->messages());
    }

    public function testFailureCarriesStatusCorrelationIdAndExceptionAndRethrows(): void
    {
        $error = FlowableApiException::unreachable();

        try {
            $this->audit->audited('deployment.delete', FlowableAuditLogger::VIA_CLI, 'user-1', null, ['deployment' => 'd-1'], static fn () => throw $error);
            self::fail('The exception was not rethrown.');
        } catch (FlowableApiException $e) {
            self::assertSame($error, $e);
        }

        $records = $this->logger->records();
        self::assertSame(['flowable.deployment.delete.attempt', 'flowable.deployment.delete.failure'], $this->logger->messages());
        self::assertSame(LogLevel::WARNING, $records[1]['level']);
        self::assertSame([
            'state' => 'failure',
            'via' => 'cli',
            'actor' => 'user-1',
            'deployment' => 'd-1',
            'status' => 504,
            'correlationId' => $error->getCorrelationId(),
            'exception' => FlowableApiException::class,
        ], $records[1]['context']);
    }

    public function testFailureWithoutCorrelationIdOrHttpStatus(): void
    {
        $this->audit->failure('task.complete', FlowableAuditLogger::VIA_WORKER, 'w-1', null, new BadRequestHttpException('no'));
        $this->audit->failure('task.complete', FlowableAuditLogger::VIA_WORKER, 'w-1', null, new \RuntimeException('no'));

        [$http, $other] = array_column($this->logger->records(), 'context');
        self::assertSame(['state' => 'failure', 'via' => 'worker', 'actor' => 'w-1', 'status' => 400, 'exception' => BadRequestHttpException::class], $http);
        self::assertSame(['state' => 'failure', 'via' => 'worker', 'actor' => 'w-1', 'status' => null, 'exception' => \RuntimeException::class], $other);
    }

    public function testMissingActorIsRecordedAsNull(): void
    {
        $this->audit->success('deployment.delete', FlowableAuditLogger::VIA_HTTP, null, null);

        self::assertSame(
            [['level' => LogLevel::INFO, 'message' => 'flowable.deployment.delete', 'context' => ['state' => 'success', 'via' => 'http', 'actor' => null]]],
            $this->logger->records(),
        );
    }

    public function testReservedKeysInTheCallerContextAreDropped(): void
    {
        $this->audit->attempt('task.complete', FlowableAuditLogger::VIA_HTTP, 'user-1', null, [
            'actor' => 'other',
            'state' => 'success',
            'via' => 'mcp',
            'tool' => 'x',
            'status' => 200,
            'correlationId' => 'c',
            'exception' => 'E',
            'task' => 't-1',
        ]);

        self::assertSame(
            ['state' => 'attempt', 'via' => 'http', 'actor' => 'user-1', 'task' => 't-1'],
            $this->logger->records()[0]['context'],
        );
    }

    public function testUnknownOriginIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->audit->attempt('task.complete', 'queue', 'user-1', null);
    }

    public function testToolNameOutsideMcpIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->audit->attempt('task.complete', FlowableAuditLogger::VIA_HTTP, 'user-1', 'flowable_complete_task');
    }
}
// - revised 2026-11-26 (attempt/success/failure states, origin, MCP tool name, correlation id)
