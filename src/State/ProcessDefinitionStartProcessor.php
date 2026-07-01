<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowProcessInstance;

/**
 * Starts a process instance from a definition (POST /process_definitions/{id}/start).
 * The acting user's JWT sub is sent as startUserId (design D7).
 *
 * @implements ProcessorInterface<mixed, FlowProcessInstance>
 */
final class ProcessDefinitionStartProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowProcessInstance
    {
        $body = $this->validator->validateRaw($this->rawBody(), $this->schemaPath('FlowProcessDefinition', 'start'));
        $client = $this->client($body);

        $payload = ['processDefinitionId' => (string) ($uriVariables['id'] ?? '')];
        if (isset($body['businessKey'])) {
            $payload['businessKey'] = $body['businessKey'];
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
        $this->audit('process_definition.start', ['definition' => $payload['processDefinitionId'], 'instance' => $instance->id]);

        return $instance;
    }
}
