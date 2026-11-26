<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Deletes a DMN deployment (DELETE /dmn_deployments/{id}), dropping its decision
 * definitions. Unlike the process engine there is no cascade option — decisions
 * carry no running instances.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DmnDeploymentDeleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = (string) ($uriVariables['id'] ?? '');

        $client = $this->client(context: $context);
        $this->audited(
            'dmn.deployment.delete',
            $context,
            ['deployment' => $id],
            static fn () => $client->deleteDmnDeployment($id),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
