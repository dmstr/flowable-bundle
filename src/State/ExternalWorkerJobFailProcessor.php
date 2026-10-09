<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Reports an acquired external worker job as failed
 * (POST /external_worker_jobs/{id}/fail).
 *
 * Backoff and dead-lettering belong to the engine: retries/retryTimeout are
 * only forwarded when the caller states them, otherwise the engine's own
 * decrement-and-reschedule applies. No `triggeredBy` actor variable — the actor
 * here is the worker (see ExternalWorkerJobCompleteProcessor).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ExternalWorkerJobFailProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody($context), $this->schemaPath('FlowExternalWorkerJob', 'fail'));
        $client = $this->client($body, $context);

        $jobId = (string) ($uriVariables['id'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');

        $payload = ['workerId' => $workerId];
        foreach (['retryTimeout', 'errorMessage', 'errorDetails'] as $key) {
            if (isset($body[$key])) {
                $payload[$key] = (string) $body[$key];
            }
        }
        if (isset($body['retries'])) {
            $payload['retries'] = (int) $body['retries'];
        }

        $client->failExternalWorkerJob($jobId, $payload);
        $this->audit('external_worker.fail', [
            'job' => $jobId,
            'worker' => $workerId,
            'error' => $payload['errorMessage'] ?? null,
        ]);

        return null;
    }
}
