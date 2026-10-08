<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Dmstr\Flowable\ApiResource\FlowJob;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Serves FlowJob from one of the engine's five job collections, selected by the
 * `kind` query parameter (see FlowJob's class docblock for why they share a
 * resource). The item operation honours `kind` as well, because an id is only
 * resolvable in its own collection.
 *
 * @implements ProviderInterface<FlowJob>
 */
final class FlowJobProvider extends AbstractFlowableProvider implements ProviderInterface
{
    public const KINDS = ['async', 'timer', 'suspended', 'deadletter', 'history'];

    public const DEFAULT_KIND = 'async';

    /** Operation extra property that pins the job kind. */
    public const FIXED_KIND = 'dmstr_flowable_job_kind';

    private const FILTERS = [
        'id',
        'executionId',
        'processInstanceId',
        'processDefinitionId',
        'elementId',
        'elementName',
        'handlerType',
        'correlationId',
        'exceptionMessage',
        'timersOnly',
        'messagesOnly',
        'withException',
        'withRetriesLeft',
        'executable',
        'locked',
        'unlocked',
        'scopeId',
        'subScopeId',
        'scopeType',
        'scopeDefinitionId',
        'tenantId',
        'tenantIdLike',
        'withoutProcessInstanceId',
        'withoutScopeId',
        'withoutScopeType',
        'withoutTenantId',
    ];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $client = $this->client($context);
        // An operation may pin the kind (the MCP tool
        // flowable_system_list_deadletter_jobs); a pinned kind wins over the
        // `kind` argument.
        $kind = self::normalizeKind($operation->getExtraProperties()[self::FIXED_KIND] ?? $this->queryParam('kind', $context));

        if ($operation instanceof CollectionOperationInterface) {
            $query = array_merge(
                $this->relationFilters([
                    'processInstance' => 'processInstanceId',
                    'processDefinition' => 'processDefinitionId',
                    'execution' => 'executionId',
                ], $context),
                // createTime is accepted by every job collection (verified
                // against flowable-rest 8.0.0); `retries`, for instance, is not.
                $this->listQuery(self::FILTERS, 'createTime', context: $context),
            );
            $envelope = $this->listByKind($client, $kind, $query);

            return $this->paginate($envelope, static fn (array $row): FlowJob => FlowJob::fromApi($row, $kind));
        }

        $data = $client->findJob((string) ($uriVariables['id'] ?? ''), $kind);

        return $data !== null ? FlowJob::fromApi($data, $kind) : null;
    }

    /**
     * Validate the requested kind, so a typo yields a 400 with the allowed
     * values instead of a stray engine error.
     *
     * @return 'async'|'timer'|'suspended'|'deadletter'|'history'
     */
    public static function normalizeKind(?string $kind): string
    {
        if ($kind === null || $kind === '') {
            return self::DEFAULT_KIND;
        }
        if (!\in_array($kind, self::KINDS, true)) {
            throw new BadRequestHttpException(sprintf(
                'Unknown job kind "%s". Allowed: %s.',
                $kind,
                implode(', ', self::KINDS),
            ));
        }

        /** @var 'async'|'timer'|'suspended'|'deadletter'|'history' $kind */
        return $kind;
    }

    /**
     * @param array<string,scalar> $query
     * @return array<string,mixed> Flowable list envelope
     */
    private function listByKind(FlowableClientInterface $client, string $kind, array $query): array
    {
        return match ($kind) {
            'timer' => $client->listTimerJobs($query),
            'suspended' => $client->listSuspendedJobs($query),
            'deadletter' => $client->listDeadLetterJobs($query),
            'history' => $client->listHistoryJobs($query),
            default => $client->listJobs($query),
        };
    }
}
// - revised 2026-10-08 (kind pinned by an operation extra property, for the MCP deadletter tool)
