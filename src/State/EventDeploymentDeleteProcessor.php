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

        $client = $this->client(context: $context);
        $this->audited(
            'event_deployment.delete',
            $context,
            ['deployment' => $id],
            static fn () => $client->deleteEventDeployment($id),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
