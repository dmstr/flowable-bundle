<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Worker;

use Dmstr\Flowable\Worker\ExternalWorkerOutcome;
use PHPUnit\Framework\TestCase;

/**
 * The value each factory produces. The engine payloads built from them are
 * covered in {@see ExternalWorkerRunnerTest}.
 */
final class ExternalWorkerOutcomeTest extends TestCase
{
    public function testComplete(): void
    {
        $outcome = ExternalWorkerOutcome::complete(['a' => 1]);

        self::assertSame(ExternalWorkerOutcome::KIND_COMPLETE, $outcome->kind);
        self::assertSame(['a' => 1], $outcome->variables);
        self::assertNull($outcome->errorCode);
        self::assertNull($outcome->retries);
        self::assertNull($outcome->retryTimeout);
        self::assertNull($outcome->message);
        self::assertNull($outcome->details);
        self::assertSame([], ExternalWorkerOutcome::complete()->variables);
    }

    public function testFailWithoutArgumentsLeavesEverythingToTheEngine(): void
    {
        $outcome = ExternalWorkerOutcome::fail();

        self::assertSame(ExternalWorkerOutcome::KIND_FAIL, $outcome->kind);
        self::assertSame([], $outcome->variables);
        self::assertNull($outcome->errorCode);
        self::assertNull($outcome->retries);
        self::assertNull($outcome->retryTimeout);
        self::assertNull($outcome->message);
        self::assertNull($outcome->details);
    }

    public function testFailCarriesWhatWasPassed(): void
    {
        $outcome = ExternalWorkerOutcome::fail(retries: 0, retryTimeout: 'PT5M', message: 'broken', details: 'trace');

        self::assertSame(ExternalWorkerOutcome::KIND_FAIL, $outcome->kind);
        self::assertSame(0, $outcome->retries);
        self::assertSame('PT5M', $outcome->retryTimeout);
        self::assertSame('broken', $outcome->message);
        self::assertSame('trace', $outcome->details);
    }

    public function testBpmnError(): void
    {
        $outcome = ExternalWorkerOutcome::bpmnError('E1', ['b' => 'x']);

        self::assertSame(ExternalWorkerOutcome::KIND_BPMN_ERROR, $outcome->kind);
        self::assertSame('E1', $outcome->errorCode);
        self::assertSame(['b' => 'x'], $outcome->variables);
        self::assertNull($outcome->retries);
        self::assertNull($outcome->retryTimeout);
        self::assertNull($outcome->message);
        self::assertNull($outcome->details);
    }
}
