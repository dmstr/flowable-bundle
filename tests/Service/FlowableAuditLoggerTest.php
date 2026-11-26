<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:27:39 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Service;

use Dmstr\Flowable\Service\FlowableAuditLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Pins the shape of an audit entry: message "flowable.<action>", level info,
 * the caller's context first and "actor" appended unless the context already
 * has one.
 */
final class FlowableAuditLoggerTest extends TestCase
{
    public function testWritesPrefixedInfoEntryWithActorAppended(): void
    {
        $logger = self::logger();

        (new FlowableAuditLogger($logger))->record('process_instance.start', 'user-1', ['id' => 'pi-1', 'key' => 'order']);

        self::assertSame(
            [[LogLevel::INFO, 'flowable.process_instance.start', ['id' => 'pi-1', 'key' => 'order', 'actor' => 'user-1']]],
            $logger->records,
        );
    }

    public function testMissingActorIsRecordedAsNull(): void
    {
        $logger = self::logger();

        (new FlowableAuditLogger($logger))->record('deployment.delete', null);

        self::assertSame([[LogLevel::INFO, 'flowable.deployment.delete', ['actor' => null]]], $logger->records);
    }

    public function testActorInTheContextWins(): void
    {
        $logger = self::logger();

        (new FlowableAuditLogger($logger))->record('task.complete', 'user-1', ['actor' => 'other', 'id' => 't-1']);

        self::assertSame(
            [[LogLevel::INFO, 'flowable.task.complete', ['actor' => 'other', 'id' => 't-1']]],
            $logger->records,
        );
    }

    /**
     * @return AbstractLogger&object{records: list<array{string, string, array<string, mixed>}>}
     */
    private static function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<array{string, string, array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }
}
