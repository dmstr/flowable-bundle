<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowExternalWorkerJob;

/**
 * @implements ProviderInterface<FlowExternalWorkerJob>
 */
final class FlowExternalWorkerJobProvider extends AbstractFlowableProvider implements ProviderInterface
{
    private const FILTERS = [
        'id',
        'executionId',
        'processInstanceId',
        'processDefinitionId',
        'elementId',
        'elementName',
        'exceptionMessage',
        'scopeId',
        'subScopeId',
        'scopeType',
        'scopeDefinitionId',
        'tenantId',
        'tenantIdLike',
        'unlocked',
        'withException',
        'withoutProcessInstanceId',
        'withoutScopeId',
        'withoutScopeType',
        'withoutTenantId',
    ];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client($context);

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters([
                    'processInstance' => 'processInstanceId',
                    'processDefinition' => 'processDefinitionId',
                    'execution' => 'executionId',
                ], $context),
                // Flowable has no updated_at; createTime is the closest thing to
                // "newest first" for a worker queue.
                $this->listQuery(self::FILTERS, 'createTime', context: $context),
            );
            $envelope = $client->listExternalWorkerJobs($query);

            return $this->paginate($envelope, FlowExternalWorkerJob::fromApi(...));
        }

        $data = $client->findExternalWorkerJob((string) ($uriVariables['id'] ?? ''));

        return $data !== null ? FlowExternalWorkerJob::fromApi($data) : null;
    }
}
// - revised 2026-10-08 (processDefinition relation filter)
