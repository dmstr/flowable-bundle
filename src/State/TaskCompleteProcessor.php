<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Completes a user task (POST /tasks/{id}/complete), recording the acting
 * user as the triggeredBy variable (design D7).
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class TaskCompleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowTask', 'complete'));
        $client = $this->client($body);

        $payload = ['action' => 'complete'];
        $variables = $this->variablesWithActor($body);
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $taskId = (string) ($uriVariables['id'] ?? '');
        $client->completeTask($taskId, $payload);
        $this->audit('task.complete', ['task' => $taskId]);

        return null;
    }
}
