<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;

/**
 * Deletes a deployment (DELETE /deployments/{id}). Pass ?cascade=true to also
 * remove the deployment's running and historic process instances.
 *
 * @implements ProcessorInterface<mixed, null>
 */
final class DeploymentDeleteProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $id = (string) ($uriVariables['id'] ?? '');
        $cascade = filter_var(
            $this->requestStack->getCurrentRequest()?->query->get('cascade'),
            \FILTER_VALIDATE_BOOL,
        );

        $this->client()->deleteDeployment($id, $cascade);
        $this->audit('deployment.delete', ['deployment' => $id, 'cascade' => $cascade]);

        return null;
    }
}
