<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Deletes an event registry deployment (DELETE /event_deployments/{id}), dropping
 * its event and channel definitions. Like the DMN engine there is no cascade
 * option — definitions carry no running instances of their own.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class EventDeploymentDeleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = (string) ($uriVariables['id'] ?? '');

        $this->client(context: $context)->deleteEventDeployment($id);
        $this->audit('event_deployment.delete', ['deployment' => $id]);

        return null;
    }
}
