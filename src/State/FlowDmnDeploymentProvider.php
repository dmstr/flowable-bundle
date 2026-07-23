<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowDmnDeployment;

/**
 * @implements ProviderInterface<FlowDmnDeployment>
 */
final class FlowDmnDeploymentProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = ['name', 'category', 'tenantId'];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client();

        if ($operation instanceof CollectionOperationInterface) {
            $envelope = $client->listDmnDeployments($this->listQuery(self::FILTERS, 'deployTime'));

            return $this->paginate($envelope, FlowDmnDeployment::fromApi(...));
        }

        $data = $client->findDmnDeployment((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowDmnDeployment::fromApi($data) : null;
    }
}
