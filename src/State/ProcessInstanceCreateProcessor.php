<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowProcessInstance;

/**
 * Starts a process instance by definition key or id (POST /process_instances).
 *
 * @implements ProcessorInterface<mixed, FlowProcessInstance>
 */
final class ProcessInstanceCreateProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowProcessInstance
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowProcessInstance', 'create'));
        $client = $this->client($body);

        $payload = [];
        foreach (['processDefinitionId', 'processDefinitionKey', 'businessKey'] as $key) {
            if (isset($body[$key])) {
                $payload[$key] = $body[$key];
            }
        }
        // startUserId is kept for engines that honour it; the za7 actor is also
        // recorded as the startedBy variable since Flowable 7.2 ignores it.
        $variables = $this->variablesWithActor($body, 'startedBy');
        if ($variables !== []) {
            $payload['variables'] = $variables;
        }
        $actor = $this->actingUser->currentUserId();
        if ($actor !== null) {
            $payload['startUserId'] = $actor;
        }

        $instance = FlowProcessInstance::fromApi($client->startProcessInstance($payload));
        $this->audit('process_instance.create', ['instance' => $instance->id]);

        return $instance;
    }
}
