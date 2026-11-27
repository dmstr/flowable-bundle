<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Worker;

use Dmstr\Flowable\Worker\ExternalWorkerJob;
use PHPUnit\Framework\TestCase;

final class ExternalWorkerJobTest extends TestCase
{
    public function testFromApiReadsAnAcquiredJob(): void
    {
        $data = [
            'id' => 'job-1',
            'processInstanceId' => 'pi-1',
            'executionId' => 'ex-1',
            'elementId' => 'task-1',
            'elementName' => 'Task one',
            'retries' => 3,
            'variables' => [
                ['name' => 'a', 'type' => 'integer', 'value' => 1],
                ['name' => 'b', 'type' => 'string', 'value' => 'x'],
                ['name' => 'c', 'type' => 'json', 'value' => ['k' => 'v']],
                ['name' => 'd', 'type' => 'string'],
            ],
        ];

        $job = ExternalWorkerJob::fromApi($data, 'topic-a');

        self::assertSame('job-1', $job->id);
        self::assertSame('topic-a', $job->topic);
        self::assertSame('pi-1', $job->processInstanceId);
        self::assertSame('ex-1', $job->executionId);
        self::assertSame('task-1', $job->elementId);
        self::assertSame('Task one', $job->elementName);
        self::assertSame(3, $job->retries);
        self::assertSame(['a' => 1, 'b' => 'x', 'c' => ['k' => 'v'], 'd' => null], $job->variables);
        self::assertSame($data, $job->raw);
        self::assertSame(1, $job->variable('a'));
        self::assertSame('fallback', $job->variable('missing', 'fallback'));
        self::assertSame('fallback', $job->variable('d', 'fallback'));
    }

    public function testFromApiToleratesMissingFields(): void
    {
        $job = ExternalWorkerJob::fromApi([], 'topic-a');

        self::assertSame('', $job->id);
        self::assertSame('topic-a', $job->topic);
        self::assertNull($job->processInstanceId);
        self::assertNull($job->executionId);
        self::assertNull($job->elementId);
        self::assertNull($job->elementName);
        self::assertSame(0, $job->retries);
        self::assertSame([], $job->variables);
        self::assertSame([], $job->raw);
    }

    public function testFromApiCastsScalarsAndKeepsAnEmptyId(): void
    {
        $job = ExternalWorkerJob::fromApi(['id' => '', 'processInstanceId' => 42, 'retries' => '2'], 'topic-a');

        self::assertSame('', $job->id);
        self::assertSame('42', $job->processInstanceId);
        self::assertSame(2, $job->retries);
    }

    public function testFromApiSkipsMalformedVariables(): void
    {
        $job = ExternalWorkerJob::fromApi(['id' => 'job-1', 'variables' => ['plain', ['value' => 1], ['name' => 'ok', 'value' => true]]], 'topic-a');

        self::assertSame(['ok' => true], $job->variables);
        self::assertSame([], ExternalWorkerJob::fromApi(['id' => 'job-1', 'variables' => 'not-a-list'], 'topic-a')->variables);
    }
}
