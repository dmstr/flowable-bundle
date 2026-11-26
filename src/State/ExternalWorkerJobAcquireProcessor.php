<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowExternalWorkerJob;

/**
 * Acquires (locks) external worker jobs of one topic
 * (POST /external_worker_jobs/acquire).
 *
 * The engine answers with a bare JSON array of locked jobs, so this is a POST
 * that RETURNS data: the response is a FlowExternalWorkerJob whose `id` is the
 * *worker id* (the only identifier a batch result has) and whose `acquired`
 * property carries the jobs — the same shape trick FlowDecision::$result uses
 * for POST /decisions/execute. An empty `acquired` means the topic had no work.
 *
 * No `triggeredBy` actor variable is set here: the actor of a worker operation
 * is the worker, not a za7 user. The audit log records both.
 *
 * @implements ProcessorInterface<mixed, FlowExternalWorkerJob>
 */
final class ExternalWorkerJobAcquireProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public const string INPUT_SCHEMA = 'FlowExternalWorkerJob/acquire.input.json';

    private const DEFAULT_LOCK_DURATION = 'PT10M';

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowExternalWorkerJob
    {
        $body = $this->validatedBody($context);
        $client = $this->client($body, $context);

        $topic = (string) ($body['topic'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');

        $payload = [
            'topic' => $topic,
            'workerId' => $workerId,
            'lockDuration' => (string) ($body['lockDuration'] ?? self::DEFAULT_LOCK_DURATION),
            'numberOfTasks' => (int) ($body['numberOfTasks'] ?? 1),
        ];
        // numberOfRetries and scopeType are only sent when given, so the engine
        // defaults (5 retries, any scope) stay in charge.
        if (isset($body['numberOfRetries'])) {
            $payload['numberOfRetries'] = (int) $body['numberOfRetries'];
        }
        if (isset($body['scopeType'])) {
            $payload['scopeType'] = (string) $body['scopeType'];
        }

        $jobs = $this->audited(
            'external_worker.acquire',
            $context,
            ['topic' => $topic, 'worker' => $workerId],
            static fn (): array => $client->acquireExternalWorkerJobs($payload),
            static fn (array $jobs): array => ['acquired' => \count($jobs)],
        );

        $result = new FlowExternalWorkerJob();
        $result->id = $workerId;
        $result->lockOwner = $workerId;
        $result->acquired = $jobs;

        return $result;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (input file declared once in INPUT_SCHEMA, read by validatedBody())
