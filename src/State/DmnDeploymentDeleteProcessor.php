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

        $this->client()->deleteDmnDeployment($id);
        $this->audit('dmn.deployment.delete', ['deployment' => $id]);

        return null;
    }
}
