<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowDeployment;

/**
 * @implements ProviderInterface<FlowDeployment>
 */
final class FlowDeploymentProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['name', 'category', 'tenantId'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $envelope = $client->listDeployments($this->listQuery(self::FILTERS));

            return $this->paginate($envelope, FlowDeployment::fromApi(...));
        }

        $data = $client->findDeployment((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowDeployment::fromApi($data) : null;
    }
}
