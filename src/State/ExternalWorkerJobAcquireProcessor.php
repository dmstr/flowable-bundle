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
    private const DEFAULT_LOCK_DURATION = 'PT10M';

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowExternalWorkerJob
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowExternalWorkerJob', 'acquire'));
        $client = $this->client($body);

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

        $jobs = $client->acquireExternalWorkerJobs($payload);

        $result = new FlowExternalWorkerJob();
        $result->id = $workerId;
        $result->lockOwner = $workerId;
        $result->acquired = $jobs;
        $this->audit('external_worker.acquire', [
            'topic' => $topic,
            'worker' => $workerId,
            'acquired' => \count($jobs),
        ]);

        return $result;
    }
}
