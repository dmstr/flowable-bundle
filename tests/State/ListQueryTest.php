<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:28:46 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\State\AbstractFlowableProvider;
use Dmstr\Flowable\State\FlowChannelDefinitionProvider;
use Dmstr\Flowable\State\FlowDecisionProvider;
use Dmstr\Flowable\State\FlowDeploymentProvider;
use Dmstr\Flowable\State\FlowDmnDeploymentProvider;
use Dmstr\Flowable\State\FlowEventDefinitionProvider;
use Dmstr\Flowable\State\FlowEventDeploymentProvider;
use Dmstr\Flowable\State\FlowExecutionProvider;
use Dmstr\Flowable\State\FlowExternalWorkerJobProvider;
use Dmstr\Flowable\State\FlowHistoricActivityProvider;
use Dmstr\Flowable\State\FlowHistoricDecisionExecutionProvider;
use Dmstr\Flowable\State\FlowHistoricProcessInstanceProvider;
use Dmstr\Flowable\State\FlowHistoricTaskProvider;
use Dmstr\Flowable\State\FlowHistoricVariableProvider;
use Dmstr\Flowable\State\FlowJobProvider;
use Dmstr\Flowable\State\FlowProcessDefinitionProvider;
use Dmstr\Flowable\State\FlowProcessInstanceProvider;
use Dmstr\Flowable\State\FlowTaskProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * The query every list provider sends to the engine for an HTTP collection
 * request (AbstractFlowableProvider::listQuery() and relationFilters()),
 * per provider:
 *
 *  - the default sort: the provider's time field, descending, when the client
 *    names no `sort`; the six providers without a default send neither
 *    `sort` nor `order` (not even when the client gives `order` alone);
 *  - an explicit client `sort` wins over the default; its `order` is
 *    compared case-insensitively, and anything other than "asc" (also an
 *    unknown direction and an empty string) becomes "desc";
 *  - the filters on the provider's allow-list reach the engine unchanged,
 *    every other query parameter (the filters of the other providers, raw
 *    `start`/`size`, unknown names) does not;
 *  - a relation filter (the property name the API Platform Admin filters by)
 *    becomes the engine's id field, whether it is given as an IRI, an
 *    absolute URL, a bare id or the IRI of another resource: only the last
 *    path segment is used, URL-decoded, and the resource part of the IRI is
 *    not checked (documented in idFromIri()); a flat id filter of the same
 *    engine field wins over the relation;
 *  - the page size is clamped to 1..200 (0, a negative and a non-numeric
 *    value become 1, anything above 200 becomes 200), a page below 1 is the
 *    first page.
 *
 * The tables below record the code as it is; a change to a provider's list
 * shows up here.
 */
final class ListQueryTest extends StateTestCase
{
    private const array ENVELOPE = ['data' => [], 'total' => 0, 'start' => 0, 'size' => 30];

    /**
     * Every list provider: client method, default sort field (null for none),
     * filter allow-list, relation filters (relation => engine id field).
     *
     * @var array<class-string<AbstractFlowableProvider>, array{string, ?string, list<string>, array<string, string>}>
     */
    private const array PROVIDERS = [
        FlowTaskProvider::class => ['listTasks', 'createTime',
            ['processInstanceId', 'assignee', 'taskDefinitionKey'],
            ['processInstance' => 'processInstanceId', 'processDefinition' => 'processDefinitionId']],
        FlowExternalWorkerJobProvider::class => ['listExternalWorkerJobs', 'createTime',
            ['id', 'executionId', 'processInstanceId', 'processDefinitionId', 'elementId', 'elementName',
                'exceptionMessage', 'scopeId', 'subScopeId', 'scopeType', 'scopeDefinitionId', 'tenantId',
                'tenantIdLike', 'unlocked', 'withException', 'withoutProcessInstanceId', 'withoutScopeId',
                'withoutScopeType', 'withoutTenantId'],
            ['processInstance' => 'processInstanceId', 'processDefinition' => 'processDefinitionId', 'execution' => 'executionId']],
        FlowJobProvider::class => ['listJobs', 'createTime',
            ['id', 'executionId', 'processInstanceId', 'processDefinitionId', 'elementId', 'elementName',
                'handlerType', 'correlationId', 'exceptionMessage', 'timersOnly', 'messagesOnly', 'withException',
                'withRetriesLeft', 'executable', 'locked', 'unlocked', 'scopeId', 'subScopeId', 'scopeType',
                'scopeDefinitionId', 'tenantId', 'tenantIdLike', 'withoutProcessInstanceId', 'withoutScopeId',
                'withoutScopeType', 'withoutTenantId'],
            ['processInstance' => 'processInstanceId', 'processDefinition' => 'processDefinitionId', 'execution' => 'executionId']],
        FlowChannelDefinitionProvider::class => ['listChannelDefinitions', 'createTime',
            ['key', 'keyLike', 'name', 'nameLike', 'category', 'categoryLike', 'categoryNotEquals', 'version',
                'latest', 'resourceName', 'implementation', 'onlyInbound', 'onlyOutbound', 'createTimeAfter',
                'createTimeBefore', 'deploymentId', 'parentDeploymentId', 'tenantId'],
            ['eventDeployment' => 'deploymentId']],
        FlowProcessInstanceProvider::class => ['listProcessInstances', 'startTime',
            ['processDefinitionKey', 'processDefinitionId', 'businessKey'],
            ['processDefinition' => 'processDefinitionId']],
        FlowHistoricProcessInstanceProvider::class => ['listHistoricProcessInstances', 'startTime',
            ['finished', 'processDefinitionKey', 'processDefinitionId', 'businessKey', 'startedBy', 'finishedAfter', 'finishedBefore'],
            ['processDefinition' => 'processDefinitionId']],
        FlowHistoricDecisionExecutionProvider::class => ['listHistoricDecisionExecutions', 'startTime',
            ['decisionKey', 'decisionDefinitionId', 'instanceId', 'executionId'],
            []],
        FlowDeploymentProvider::class => ['listDeployments', 'deployTime',
            ['name', 'category', 'tenantId'],
            []],
        FlowDmnDeploymentProvider::class => ['listDmnDeployments', 'deployTime',
            ['name', 'category', 'tenantId'],
            []],
        FlowEventDeploymentProvider::class => ['listEventDeployments', 'deployTime',
            ['name', 'nameLike', 'category', 'categoryNotEquals', 'parentDeploymentId', 'parentDeploymentIdLike',
                'tenantId', 'tenantIdLike', 'withoutTenantId'],
            []],
        FlowHistoricTaskProvider::class => ['listHistoricTasks', 'endTime',
            ['processInstanceId', 'finished', 'taskDefinitionKey', 'taskAssignee', 'processDefinitionKey'],
            ['historicProcessInstance' => 'processInstanceId', 'processDefinition' => 'processDefinitionId']],
        FlowHistoricVariableProvider::class => ['listHistoricVariables', null,
            ['processInstanceId', 'taskId', 'variableName'],
            ['historicProcessInstance' => 'processInstanceId', 'historicTask' => 'taskId']],
        FlowHistoricActivityProvider::class => ['listHistoricActivities', null,
            ['processInstanceId', 'activityId', 'activityType', 'finished'],
            ['historicProcessInstance' => 'processInstanceId']],
        FlowProcessDefinitionProvider::class => ['listProcessDefinitions', null,
            ['latest', 'key', 'category'],
            ['deployment' => 'deploymentId']],
        FlowEventDefinitionProvider::class => ['listEventDefinitions', null,
            ['key', 'keyLike', 'name', 'nameLike', 'category', 'categoryLike', 'categoryNotEquals', 'version',
                'latest', 'resourceName', 'deploymentId', 'parentDeploymentId', 'tenantId'],
            ['eventDeployment' => 'deploymentId']],
        FlowExecutionProvider::class => ['listExecutions', null,
            ['processInstanceId', 'activityId', 'parentId'],
            ['processInstance' => 'processInstanceId']],
        FlowDecisionProvider::class => ['listDecisions', null,
            ['key', 'name', 'category', 'deploymentId', 'latest'],
            ['dmnDeployment' => 'deploymentId']],
    ];

    /** The collection path of a relation's IRI */
    private const array RELATION_COLLECTIONS = [
        'processInstance' => 'process_instances',
        'processDefinition' => 'process_definitions',
        'execution' => 'executions',
        'deployment' => 'deployments',
        'dmnDeployment' => 'dmn_deployments',
        'eventDeployment' => 'event_deployments',
        'historicProcessInstance' => 'historic_process_instances',
        'historicTask' => 'historic_tasks',
    ];

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>}>
     */
    public static function providers(): iterable
    {
        foreach (array_keys(self::PROVIDERS) as $class) {
            yield self::shortName($class) => [$class];
        }
    }

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>, string}>
     */
    public static function providersWithDefaultSort(): iterable
    {
        foreach (self::PROVIDERS as $class => [, $sort]) {
            if ($sort !== null) {
                yield self::shortName($class) => [$class, $sort];
            }
        }
    }

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>}>
     */
    public static function providersWithoutDefaultSort(): iterable
    {
        foreach (self::PROVIDERS as $class => [, $sort]) {
            if ($sort === null) {
                yield self::shortName($class) => [$class];
            }
        }
    }

    public function testTheTablesNameElevenProvidersWithAndSixWithoutDefaultSort(): void
    {
        self::assertCount(11, iterator_to_array(self::providersWithDefaultSort()));
        self::assertCount(6, iterator_to_array(self::providersWithoutDefaultSort()));
    }

    // --- sorting -------------------------------------------------------------------------

    #[DataProvider('providersWithDefaultSort')]
    public function testDefaultSortIsTheTimeFieldDescending(string $class, string $sort): void
    {
        self::assertSame(
            ['start' => 0, 'size' => 30, 'sort' => $sort, 'order' => 'desc'],
            $this->query($class, []),
        );
    }

    #[DataProvider('providersWithDefaultSort')]
    public function testOrderAloneKeepsTheDefaultSortField(string $class, string $sort): void
    {
        self::assertSame(
            ['start' => 0, 'size' => 30, 'sort' => $sort, 'order' => 'asc'],
            $this->query($class, ['order' => 'asc']),
        );
    }

    #[DataProvider('providersWithoutDefaultSort')]
    public function testWithoutDefaultNeitherSortNorOrderReachesTheEngine(string $class): void
    {
        self::assertSame(['start' => 0, 'size' => 30], $this->query($class, []));
        self::assertSame(['start' => 0, 'size' => 30], $this->query($class, ['order' => 'asc']));
    }

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>, string, string}>
     */
    public static function explicitSorts(): iterable
    {
        foreach (array_keys(self::PROVIDERS) as $class) {
            foreach (['ASC' => 'asc', 'asc' => 'asc', 'Desc' => 'desc', 'sideways' => 'desc', '' => 'desc'] as $order => $expected) {
                yield self::shortName($class) . ' order "' . $order . '"' => [$class, $order, $expected];
            }
        }
    }

    #[DataProvider('explicitSorts')]
    public function testExplicitSortWinsAndOnlyAscStaysAscending(string $class, string $order, string $expected): void
    {
        self::assertSame(
            ['start' => 0, 'size' => 30, 'sort' => 'name', 'order' => $expected],
            $this->query($class, ['sort' => 'name', 'order' => $order]),
        );
    }

    #[DataProvider('providers')]
    public function testEmptySortIsNoSort(string $class): void
    {
        $sort = self::PROVIDERS[$class][1];
        $expected = ['start' => 0, 'size' => 30];
        if ($sort !== null) {
            $expected += ['sort' => $sort, 'order' => 'desc'];
        }

        self::assertSame($expected, $this->query($class, ['sort' => '']));
    }

    public function testEveryJobKindSortsByCreateTime(): void
    {
        $methods = [
            'async' => 'listJobs',
            'timer' => 'listTimerJobs',
            'suspended' => 'listSuspendedJobs',
            'deadletter' => 'listDeadLetterJobs',
            'history' => 'listHistoryJobs',
        ];
        self::assertSame(FlowJobProvider::KINDS, array_keys($methods));

        foreach ($methods as $kind => $method) {
            self::assertSame(
                ['start' => 0, 'size' => 30, 'sort' => 'createTime', 'order' => 'desc'],
                $this->query(FlowJobProvider::class, ['kind' => $kind], $method),
                $kind,
            );
        }
    }

    // --- filters -------------------------------------------------------------------------

    #[DataProvider('providers')]
    public function testAllowedFiltersReachTheEngineAndOthersDoNot(string $class): void
    {
        [, $sort, $filters, $relations] = self::PROVIDERS[$class];

        $allowed = [];
        foreach ($filters as $filter) {
            $allowed[$filter] = 'v-' . $filter;
        }
        $others = [];
        foreach (self::PROVIDERS as [, , $otherFilters, $otherRelations]) {
            foreach ([...$otherFilters, ...array_keys($otherRelations)] as $name) {
                if (!\in_array($name, $filters, true) && !isset($relations[$name])) {
                    $others[$name] = 'other';
                }
            }
        }
        $others += ['unknownFilter' => 'x', 'start' => '99', 'size' => '99', 'variables' => 'x'];
        self::assertNotSame([], $others);

        $expected = ['start' => 0, 'size' => 30];
        if ($sort !== null) {
            $expected += ['sort' => $sort, 'order' => 'desc'];
        }
        $expected += $allowed;

        self::assertSame(self::sorted($expected), self::sorted($this->query($class, $allowed + $others)));
    }

    #[DataProvider('providers')]
    public function testEmptyFilterValueIsDropped(string $class): void
    {
        $filter = self::PROVIDERS[$class][2][0];

        self::assertArrayNotHasKey($filter, $this->query($class, [$filter => '']));
    }

    // --- relation filters ----------------------------------------------------------------

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>, string, string, string}>
     */
    public static function relationValues(): iterable
    {
        foreach (self::PROVIDERS as $class => [, , , $relations]) {
            foreach ($relations as $relation => $field) {
                $collection = self::RELATION_COLLECTIONS[$relation];
                // An IRI of another resource: tasks, or deployments for the task relation
                $foreign = $collection === 'historic_tasks' ? 'deployments' : 'tasks';
                $name = self::shortName($class) . ' ' . $relation;
                yield $name . ' IRI' => [$class, $relation, '/api/flowable/' . $collection . '/rel-1', $field];
                yield $name . ' IRI with trailing slash' => [$class, $relation, '/api/flowable/' . $collection . '/rel-1/', $field];
                yield $name . ' absolute URL' => [$class, $relation, 'https://app.example.com/api/flowable/' . $collection . '/rel-1?x=1', $field];
                yield $name . ' bare id' => [$class, $relation, 'rel-1', $field];
                yield $name . ' foreign IRI' => [$class, $relation, '/api/flowable/' . $foreign . '/rel-1', $field];
            }
        }
    }

    #[DataProvider('relationValues')]
    public function testRelationBecomesTheEngineIdField(string $class, string $relation, string $value, string $field): void
    {
        $query = $this->query($class, [$relation => $value]);

        self::assertSame('rel-1', $query[$field] ?? null);
        self::assertArrayNotHasKey($relation, $query);
    }

    public function testRelationIdIsUrlDecoded(): void
    {
        $query = $this->query(FlowTaskProvider::class, ['processInstance' => '/api/flowable/process_instances/a%20b%2Fc']);

        self::assertSame('a b/c', $query['processInstanceId'] ?? null);
    }

    /**
     * @return iterable<string, array{class-string<AbstractFlowableProvider>, string, string}>
     */
    public static function relationsWithFlatFilter(): iterable
    {
        foreach (self::PROVIDERS as $class => [, , $filters, $relations]) {
            foreach ($relations as $relation => $field) {
                if (\in_array($field, $filters, true)) {
                    yield self::shortName($class) . ' ' . $relation => [$class, $relation, $field];
                }
            }
        }
    }

    #[DataProvider('relationsWithFlatFilter')]
    public function testFlatIdFilterWinsOverTheRelation(string $class, string $relation, string $field): void
    {
        $query = $this->query($class, [$relation => '/api/flowable/' . self::RELATION_COLLECTIONS[$relation] . '/rel-1', $field => 'flat-2']);

        self::assertSame('flat-2', $query[$field] ?? null);
    }

    #[DataProvider('providers')]
    public function testRelationsOfOtherProvidersAreIgnored(string $class): void
    {
        $relations = self::PROVIDERS[$class][3];
        $params = [];
        foreach (array_keys(self::RELATION_COLLECTIONS) as $relation) {
            if (!isset($relations[$relation])) {
                $params[$relation] = 'rel-1';
            }
        }

        self::assertNotContains('rel-1', $this->query($class, $params));
    }

    // --- page size -----------------------------------------------------------------------

    /**
     * @return iterable<string, array{array<string, string>, int, int}> query, start, size
     */
    public static function pages(): iterable
    {
        yield 'size 0' => [['itemsPerPage' => '0'], 0, 1];
        yield 'size -5' => [['itemsPerPage' => '-5'], 0, 1];
        yield 'size not numeric' => [['itemsPerPage' => 'many'], 0, 1];
        yield 'size 1' => [['itemsPerPage' => '1'], 0, 1];
        yield 'size 200' => [['itemsPerPage' => '200'], 0, 200];
        yield 'size 201' => [['itemsPerPage' => '201'], 0, 200];
        yield 'size 100000' => [['itemsPerPage' => '100000'], 0, 200];
        yield 'page 0' => [['page' => '0', 'itemsPerPage' => '10'], 0, 10];
        yield 'page -3' => [['page' => '-3', 'itemsPerPage' => '10'], 0, 10];
        yield 'page 3' => [['page' => '3', 'itemsPerPage' => '10'], 20, 10];
        yield 'page 2 of size 201' => [['page' => '2', 'itemsPerPage' => '201'], 200, 200];
        yield 'page 2 of size 0' => [['page' => '2', 'itemsPerPage' => '0'], 1, 1];
    }

    #[DataProvider('pages')]
    public function testPageSizeIsClamped(array $params, int $start, int $size): void
    {
        foreach (array_keys(self::PROVIDERS) as $class) {
            $query = $this->query($class, $params);
            self::assertSame([$start, $size], [$query['start'], $query['size']], self::shortName($class));
        }
    }

    // --- helpers -------------------------------------------------------------------------

    /**
     * The query the provider sends for a GET collection request with $params.
     *
     * @param class-string<AbstractFlowableProvider> $class
     * @param array<string, string> $params
     * @return array<string, mixed>
     */
    private function query(string $class, array $params, ?string $method = null): array
    {
        $sent = null;
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method($method ?? self::PROVIDERS[$class][0])
            ->willReturnCallback(static function (array $query) use (&$sent): array {
                $sent = $query;

                return self::ENVELOPE;
            });

        $request = Request::create('/api/flowable/list', 'GET', $params);
        (new $class($this->locator($client), $this->requestStack($request)))
            ->provide(new GetCollection(), [], ['request' => $request]);

        self::assertIsArray($sent);

        return $sent;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function sorted(array $query): array
    {
        ksort($query);

        return $query;
    }

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
