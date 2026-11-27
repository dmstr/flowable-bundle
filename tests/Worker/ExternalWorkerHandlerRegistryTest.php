<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Worker;

use Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface;
use Dmstr\Flowable\Worker\ExternalWorkerHandlerRegistry;
use Dmstr\Flowable\Worker\ExternalWorkerJob;
use Dmstr\Flowable\Worker\ExternalWorkerOutcome;
use PHPUnit\Framework\TestCase;

final class ExternalWorkerHandlerRegistryTest extends TestCase
{
    public function testMapsEveryDeclaredTopicToItsHandler(): void
    {
        $first = $this->handler(['topic-a', 'topic-b']);
        $second = $this->handler(['topic-c']);
        $registry = new ExternalWorkerHandlerRegistry([$first, $second]);

        self::assertSame(['topic-a', 'topic-b', 'topic-c'], $registry->topics());
        self::assertSame($first, $registry->get('topic-a'));
        self::assertSame($first, $registry->get('topic-b'));
        self::assertSame($second, $registry->get('topic-c'));
        self::assertTrue($registry->has('topic-c'));
    }

    public function testUnknownTopicHasNoHandler(): void
    {
        $registry = new ExternalWorkerHandlerRegistry([$this->handler(['topic-a'])]);

        self::assertNull($registry->get('topic-x'));
        self::assertFalse($registry->has('topic-x'));
    }

    public function testNoHandlersMeansNoTopics(): void
    {
        $registry = new ExternalWorkerHandlerRegistry([$this->handler([])]);

        self::assertSame([], $registry->topics());
        self::assertSame([], (new ExternalWorkerHandlerRegistry([]))->topics());
    }

    public function testTopicClaimedByTwoHandlersFailsLoudly(): void
    {
        $registry = new ExternalWorkerHandlerRegistry([$this->handler(['topic-a']), $this->handler(['topic-b', 'topic-a'])]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('External worker topic "topic-a" is claimed by two handlers');

        $registry->get('topic-b');
    }

    public function testTopicClaimedByTwoHandlersAlsoFailsOnTopics(): void
    {
        $registry = new ExternalWorkerHandlerRegistry([$this->handler(['topic-a']), $this->handler(['topic-a'])]);

        $this->expectException(\LogicException::class);

        $registry->topics();
    }

    public function testHandlersAreReadLazilyFromTheIterable(): void
    {
        $read = false;
        $handlers = (function () use (&$read): \Generator {
            $read = true;
            yield $this->handler(['topic-a']);
        })();
        $registry = new ExternalWorkerHandlerRegistry($handlers);

        self::assertFalse($read);
        self::assertTrue($registry->has('topic-a'));
        self::assertTrue($read);
        self::assertSame(['topic-a'], $registry->topics(), 'The map is built once; the generator is not rewound.');
    }

    /**
     * @param list<string> $topics
     */
    private function handler(array $topics): ExternalWorkerHandlerInterface
    {
        return new class($topics) implements ExternalWorkerHandlerInterface {
            /** @param list<string> $topics */
            public function __construct(private readonly array $topics)
            {
            }

            public function topics(): array
            {
                return $this->topics;
            }

            public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome
            {
                return ExternalWorkerOutcome::complete();
            }
        };
    }
}
