<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Releases the lock on an acquired external worker job
 * (POST /external_worker_jobs/{id}/unacquire).
 *
 * Unacquiring costs no retry, which is exactly why it — and not `fail` — is the
 * right report when the worker cannot do the work at all. No `triggeredBy` actor
 * variable — the actor here is the worker (see
 * ExternalWorkerJobCompleteProcessor).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ExternalWorkerJobUnacquireProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody($context), $this->schemaPath('FlowExternalWorkerJob', 'unacquire'));
        $client = $this->client($body, $context);

        $jobId = (string) ($uriVariables['id'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');

        $client->unacquireExternalWorkerJob($jobId, ['workerId' => $workerId]);
        $this->audit('external_worker.unacquire', ['job' => $jobId, 'worker' => $workerId]);

        return null;
    }
}
