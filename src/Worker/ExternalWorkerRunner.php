<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Worker;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Psr\Log\LoggerInterface;

/**
 * Generic poll loop for Flowable's external worker (job) API.
 *
 * One iteration walks every topic: acquire a batch, resolve the topic's handler,
 * run it, report the outcome. The rules that matter — each one deliberate:
 *
 *  - **A handler that throws becomes a `fail` report**, carrying the exception
 *    message as errorMessage and a truncated stacktrace as errorDetails. There
 *    is intentionally NO retry loop here: the ENGINE owns backoff and
 *    dead-lettering, and duplicating that in the worker would mean two policies
 *    fighting over the same job.
 *  - **No handler for a topic ⇒ `unacquire`, never `fail`.** Failing spends one
 *    of the job's retries, so a worker deployed without its handler would burn
 *    a job's retries and dead-letter perfectly good work. Unacquire releases the
 *    lock at no cost.
 *  - **An empty round sleeps** (default 5s) instead of hammering the engine with
 *    acquire calls. A signal interrupts the sleep, so shutdown stays prompt.
 *  - **Held job ids are tracked** so a shutdown can unacquire what it still
 *    holds; without that, those jobs would stay locked until their lockDuration
 *    expires. They are released one by one on purpose — the engine's
 *    "unacquire all jobs of a worker" would also release jobs of another process
 *    that happens to share the worker id.
 *
 * Everything is logged through the dedicated `flowable` Monolog channel, so a
 * worker's decisions land next to the pass-through audit entries.
 */
final class ExternalWorkerRunner
{
    public const DEFAULT_LOCK_DURATION = 'PT10M';

    public const DEFAULT_IDLE_SLEEP_SECONDS = 5;

    public const DEFAULT_BATCH_SIZE = 1;

    /** Engine payloads are not the place for a full Java-sized stacktrace. */
    private const ERROR_DETAILS_MAX_LENGTH = 4000;

    /** @var array<string,true> job ids currently locked by this runner */
    private array $heldJobIds = [];

    private bool $stopRequested = false;

    public function __construct(
        private readonly FlowableClientLocator $locator,
        private readonly ExternalWorkerHandlerRegistry $registry,
        private readonly FlowableVariableMapper $variableMapper,
        private readonly LoggerInterface $flowableLogger,
    ) {
    }

    /**
     * Ask the loop to finish the current job and stop. Safe to call from a
     * signal handler — it only flips a flag, the graceful shutdown (including
     * unacquiring held jobs) happens on the loop's own thread of control.
     */
    public function requestStop(): void
    {
        $this->stopRequested = true;
    }

    public function isStopRequested(): bool
    {
        return $this->stopRequested;
    }

    /** @return list<string> */
    public function heldJobIds(): array
    {
        return array_keys($this->heldJobIds);
    }

    /**
     * Run the poll loop and return the number of jobs reported to the engine.
     *
     * @param list<string> $topics topics to poll; empty = every topic with a
     *   registered handler (see ExternalWorkerHandlerRegistry::topics())
     * @param string $lockDuration ISO-8601 duration the acquired jobs stay
     *   locked; must comfortably exceed the expected processing time
     * @param ?int $maxJobs stop after this many jobs (null = unlimited)
     * @param ?int $timeLimit stop after this many seconds (null = unlimited);
     *   the usual way to keep a long-running worker process fresh
     * @param bool $once run a single iteration over all topics, then return
     */
    public function run(
        string $workerId,
        array $topics = [],
        string $lockDuration = self::DEFAULT_LOCK_DURATION,
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        ?int $maxJobs = null,
        ?int $timeLimit = null,
        bool $once = false,
        ?string $apiConfiguration = null,
        int $idleSleepSeconds = self::DEFAULT_IDLE_SLEEP_SECONDS,
    ): int {
        $topics = $topics !== [] ? array_values(array_unique($topics)) : $this->registry->topics();
        if ($topics === []) {
            $this->flowableLogger->warning('flowable.external_worker.no_topics', ['worker' => $workerId]);

            return 0;
        }

        $client = $this->locator->resolve($apiConfiguration);
        $deadline = $timeLimit !== null ? microtime(true) + $timeLimit : null;
        $processed = 0;

        $this->flowableLogger->info('flowable.external_worker.start', [
            'worker' => $workerId,
            'topics' => $topics,
            'batchSize' => $batchSize,
            'lockDuration' => $lockDuration,
        ]);

        try {
            while (!$this->stopRequested) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    break;
                }

                $inRound = 0;
                foreach ($topics as $topic) {
                    if ($this->stopRequested) {
                        break;
                    }
                    $batch = $maxJobs !== null ? min($batchSize, $maxJobs - $processed) : $batchSize;
                    if ($batch < 1) {
                        break;
                    }
                    $handled = $this->pollTopic($client, $topic, $workerId, $lockDuration, $batch);
                    $inRound += $handled;
                    $processed += $handled;
                }

                if ($once || ($maxJobs !== null && $processed >= $maxJobs)) {
                    break;
                }
                if ($inRound === 0 && $idleSleepSeconds > 0 && !$this->stopRequested) {
                    sleep($idleSleepSeconds);
                }
            }
        } finally {
            $this->releaseHeld($client, $workerId);
            $this->flowableLogger->info('flowable.external_worker.stop', [
                'worker' => $workerId,
                'processed' => $processed,
                'stopRequested' => $this->stopRequested,
            ]);
        }

        return $processed;
    }

    /**
     * Acquire and handle up to $batchSize jobs of one topic.
     *
     * @return int jobs reported to the engine (a job released with `unacquire`
     *   is not counted — no work was done)
     */
    private function pollTopic(
        FlowableClientInterface $client,
        string $topic,
        string $workerId,
        string $lockDuration,
        int $batchSize,
    ): int {
        $acquired = $client->acquireExternalWorkerJobs([
            'topic' => $topic,
            'workerId' => $workerId,
            'lockDuration' => $lockDuration,
            'numberOfTasks' => $batchSize,
        ]);
        if ($acquired === []) {
            return 0;
        }

        $handler = $this->registry->get($topic);
        $handled = 0;

        foreach ($acquired as $raw) {
            $job = ExternalWorkerJob::fromApi($raw, $topic);
            if ($job->id === '') {
                continue;
            }
            $this->heldJobIds[$job->id] = true;

            try {
                if ($handler === null) {
                    // Our deployment is incomplete, not the job's fault — give
                    // the lock back without spending a retry.
                    $client->unacquireExternalWorkerJob($job->id, ['workerId' => $workerId]);
                    $this->flowableLogger->warning('flowable.external_worker.unhandled_topic', [
                        'topic' => $topic,
                        'job' => $job->id,
                        'worker' => $workerId,
                    ]);

                    continue;
                }

                try {
                    $outcome = $handler->handle($job);
                } catch (\Throwable $e) {
                    $outcome = ExternalWorkerOutcome::fail(
                        message: $e->getMessage(),
                        details: $this->truncate($e->getTraceAsString()),
                    );
                    $this->flowableLogger->error('flowable.external_worker.handler_threw', [
                        'topic' => $topic,
                        'job' => $job->id,
                        'worker' => $workerId,
                        'handler' => $handler::class,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }

                $this->report($client, $job, $workerId, $outcome);
                ++$handled;
            } finally {
                unset($this->heldJobIds[$job->id]);
            }
        }

        return $handled;
    }

    private function report(
        FlowableClientInterface $client,
        ExternalWorkerJob $job,
        string $workerId,
        ExternalWorkerOutcome $outcome,
    ): void {
        match ($outcome->kind) {
            ExternalWorkerOutcome::KIND_COMPLETE => $client->completeExternalWorkerJob($job->id, [
                'workerId' => $workerId,
                'variables' => $this->variableMapper->toFlowable($outcome->variables),
            ]),
            ExternalWorkerOutcome::KIND_BPMN_ERROR => $client->bpmnErrorExternalWorkerJob($job->id, [
                'workerId' => $workerId,
                'errorCode' => (string) $outcome->errorCode,
                'variables' => $this->variableMapper->toFlowable($outcome->variables),
            ]),
            default => $client->failExternalWorkerJob($job->id, $this->failPayload($workerId, $outcome)),
        };

        $this->flowableLogger->info('flowable.external_worker.'.$outcome->kind, [
            'topic' => $job->topic,
            'job' => $job->id,
            'worker' => $workerId,
            'processInstance' => $job->processInstanceId,
        ]);
    }

    /**
     * Only send what the caller actually decided, so the engine's own
     * decrement-and-reschedule policy stays in charge of everything else.
     *
     * @return array<string,mixed>
     */
    private function failPayload(string $workerId, ExternalWorkerOutcome $outcome): array
    {
        $payload = ['workerId' => $workerId];
        if ($outcome->retries !== null) {
            $payload['retries'] = $outcome->retries;
        }
        if ($outcome->retryTimeout !== null) {
            $payload['retryTimeout'] = $outcome->retryTimeout;
        }
        if ($outcome->message !== null) {
            $payload['errorMessage'] = $outcome->message;
        }
        if ($outcome->details !== null) {
            $payload['errorDetails'] = $this->truncate($outcome->details);
        }

        return $payload;
    }

    /**
     * Release whatever is still locked when the loop ends. Best effort: if the
     * engine is the reason the loop ended, the locks simply expire on their own.
     */
    private function releaseHeld(FlowableClientInterface $client, string $workerId): void
    {
        foreach ($this->heldJobIds() as $jobId) {
            try {
                $client->unacquireExternalWorkerJob($jobId, ['workerId' => $workerId]);
                $this->flowableLogger->info('flowable.external_worker.unacquire', [
                    'job' => $jobId,
                    'worker' => $workerId,
                    'reason' => 'shutdown',
                ]);
            } catch (\Throwable $e) {
                $this->flowableLogger->warning('flowable.external_worker.unacquire_failed', [
                    'job' => $jobId,
                    'worker' => $workerId,
                    'message' => $e->getMessage(),
                ]);
            }
            unset($this->heldJobIds[$jobId]);
        }
    }

    private function truncate(string $details): string
    {
        return \strlen($details) > self::ERROR_DETAILS_MAX_LENGTH
            ? substr($details, 0, self::ERROR_DETAILS_MAX_LENGTH).' […truncated]'
            : $details;
    }
}
