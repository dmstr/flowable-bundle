<?php
// file generated with AI assistance: Claude Code - 2026-06-22 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Triggers a waiting execution (POST /executions/{id}/trigger), recording the
 * acting user as the triggeredBy variable (design D7).
 *
 * The {id} is a Flowable execution id (a child/leaf execution that references a
 * flow element), not a process-instance id — Flowable rejects triggering the
 * process-instance execution itself.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class ExecutionTriggerProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowExecution', 'trigger'));
        $client = $this->client($body);

        $payload = ['action' => 'trigger'];
        $variables = $this->variablesWithActor($body);
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $executionId = (string) ($uriVariables['id'] ?? '');
        $client->triggerExecution($executionId, $payload);
        $this->audit('execution.trigger', ['execution' => $executionId]);

        return null;
    }
}
