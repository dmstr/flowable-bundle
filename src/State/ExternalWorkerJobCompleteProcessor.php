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
        $body = $this->validator->validateRaw($this->rawBody($context), $this->schemaPath('FlowExternalWorkerJob', 'complete'));
        $client = $this->client($body, $context);

        $jobId = (string) ($uriVariables['id'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');

        $payload = [
            'workerId' => $workerId,
            'variables' => $this->variableMapper->toFlowable($body['variables'] ?? null),
        ];
        $this->audited(
            'external_worker.complete',
            $context,
            ['job' => $jobId, 'worker' => $workerId],
            static fn () => $client->completeExternalWorkerJob($jobId, $payload),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
