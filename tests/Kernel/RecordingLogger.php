<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:31:37 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Psr\Log\AbstractLogger;

/**
 * PSR-3 logger that keeps every record in memory, so a test can assert
 * audit and error-log entries. The test kernel registers it as
 * `monolog.logger.flowable` (public); unit tests create it directly.
 *
 * Deliberately not a {@see \Symfony\Contracts\Service\ResetInterface}: the
 * kernel resets such services between two requests of the same test, which
 * would drop the records of the first request. Every test gets a fresh
 * container, hence a fresh logger; {@see self::clear()} is for a test that
 * wants to look at one step only.
 *
 * Not {@see \Symfony\Component\ErrorHandler\BufferingLogger}: that one
 * prints the records it still holds when it is destructed.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * The records with the given message, e.g. `flowable.task.complete`.
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function recordsWithMessage(string $message): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $record): bool => $record['message'] === $message,
        ));
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_column($this->records, 'message');
    }

    public function clear(): void
    {
        $this->records = [];
    }
}
