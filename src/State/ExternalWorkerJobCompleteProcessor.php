<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Reports an acquired external worker job as done
 * (POST /external_worker_jobs/{id}/complete).
 *
 * Variables are mapped with the plain variable mapper, NOT variablesWithActor():
 * the actor of a worker operation is the worker id from the body, not a za7
 * user, so injecting a `triggeredBy` process variable would be misleading.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ExternalWorkerJobCompleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowExternalWorkerJob', 'complete'));
        $client = $this->client($body);

        $jobId = (string) ($uriVariables['id'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');

        $client->completeExternalWorkerJob($jobId, [
            'workerId' => $workerId,
            'variables' => $this->variableMapper->toFlowable($body['variables'] ?? null),
        ]);
        $this->audit('external_worker.complete', ['job' => $jobId, 'worker' => $workerId]);

        return null;
    }
}
