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
            $this->inputParam('cascade', $context),
            \FILTER_VALIDATE_BOOL,
        );

        $client = $this->client(context: $context);
        $this->audited(
            'deployment.delete',
            $context,
            ['deployment' => $id, 'cascade' => $cascade],
            static fn () => $client->deleteDeployment($id, $cascade),
        );

        return null;
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
