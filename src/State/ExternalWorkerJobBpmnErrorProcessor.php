<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Raises a BPMN error from an acquired external worker job
 * (POST /external_worker_jobs/{id}/bpmnError).
 *
 * This is the *modelled* failure path (an error boundary event catches the
 * errorCode), as opposed to `fail`, which is a technical failure the engine
 * retries. No `triggeredBy` actor variable — the actor here is the worker (see
 * ExternalWorkerJobCompleteProcessor).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ExternalWorkerJobBpmnErrorProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody($context), $this->schemaPath('FlowExternalWorkerJob', 'bpmnError'));
        $client = $this->client($body, $context);

        $jobId = (string) ($uriVariables['id'] ?? '');
        $workerId = (string) ($body['workerId'] ?? '');
        $errorCode = (string) ($body['errorCode'] ?? '');

        $payload = [
            'workerId' => $workerId,
            'errorCode' => $errorCode,
            'variables' => $this->variableMapper->toFlowable($body['variables'] ?? null),
        ];
        $this->audited(
            'external_worker.bpmn_error',
            $context,
            ['job' => $jobId, 'worker' => $workerId, 'errorCode' => $errorCode],
            static fn () => $client->bpmnErrorExternalWorkerJob($jobId, $payload),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
