<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowEventDeployment;

/**
 * @implements ProviderInterface<FlowEventDeployment>
 */
final class FlowEventDeploymentProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = [
        'name', 'nameLike', 'category', 'categoryNotEquals',
        'parentDeploymentId', 'parentDeploymentIdLike',
        'tenantId', 'tenantIdLike', 'withoutTenantId',
    ];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            // Newest first. The sort field is "deployTime", not the response's
            // "deploymentTime" property — the engine rejects the latter with 400.
            $envelope = $client->listEventDeployments($this->listQuery(self::FILTERS, 'deployTime'));

            return $this->paginate($envelope, FlowEventDeployment::fromApi(...));
        }

        $data = $client->findEventDeployment((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowEventDeployment::fromApi($data) : null;
    }
}
