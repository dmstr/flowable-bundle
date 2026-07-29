<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Worker;

/**
 * Topic → handler lookup over every service tagged
 * `flowable.external_worker_handler`.
 *
 * The map is built once, lazily, from the tagged iterator. Two handlers claiming
 * the same topic is a WIRING mistake, not a runtime condition to paper over:
 * whichever one won would depend on service registration order, and the process
 * would silently take the wrong branch. So the registry throws — loudly, on the
 * first use — instead of picking a winner.
 */
final class ExternalWorkerHandlerRegistry
{
    /** @var array<string,ExternalWorkerHandlerInterface>|null */
    private ?array $map = null;

    /**
     * @param iterable<ExternalWorkerHandlerInterface> $handlers
     */
    public function __construct(
        private readonly iterable $handlers,
    ) {
    }

    /**
     * Every topic with a registered handler — the poll list of a runner started
     * without an explicit --topic.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        return array_keys($this->map());
    }

    /**
     * The handler for a topic, or null when the application registered none.
     * A null here must lead to `unacquire`, never to `fail` — see
     * ExternalWorkerRunner.
     */
    public function get(string $topic): ?ExternalWorkerHandlerInterface
    {
        return $this->map()[$topic] ?? null;
    }

    public function has(string $topic): bool
    {
        return isset($this->map()[$topic]);
    }

    /**
     * @return array<string,ExternalWorkerHandlerInterface>
     */
    private function map(): array
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $map = [];
        foreach ($this->handlers as $handler) {
            foreach ($handler->topics() as $topic) {
                $topic = (string) $topic;
                if (isset($map[$topic])) {
                    throw new \LogicException(sprintf(
                        'External worker topic "%s" is claimed by two handlers (%s and %s). '
                        .'A topic must have exactly one handler.',
                        $topic,
                        $map[$topic]::class,
                        $handler::class,
                    ));
                }
                $map[$topic] = $handler;
            }
        }

        return $this->map = $map;
    }
}
