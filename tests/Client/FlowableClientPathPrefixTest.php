<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:24 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Every public request method of {@see FlowableClient} goes to the path
 * prefix of its engine part: `/service` (process engine), `/dmn-api`,
 * `/event-registry-api` and `/external-job-api`, exactly once, right after
 * the base URL.
 */
final class FlowableClientPathPrefixTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';

    private const string PROCESS = '/service';
    private const string DMN = '/dmn-api';
    private const string EVENT = '/event-registry-api';
    private const string EXTERNAL = '/external-job-api';

    private const array PREFIXES = [self::PROCESS, self::DMN, self::EVENT, self::EXTERNAL];

    /** Public methods that send no request */
    private const array NO_REQUEST_METHODS = ['__construct', 'getEndpoint'];

    /** @var list<array{method: string, url: string, body: string}> */
    private array $requests = [];

    /**
     * Every request method: [prefix, HTTP method, path after the base URL, call].
     *
     * @return array<string, array{string, string, string, \Closure(FlowableClient): mixed}>
     */
    private static function calls(): array
    {
        return [
            // process engine
            'getHealthInfo' => [self::PROCESS, 'GET', '/service/management/engine', static fn (FlowableClient $c) => $c->getHealthInfo()],
            'listDeployments' => [self::PROCESS, 'GET', '/service/repository/deployments', static fn (FlowableClient $c) => $c->listDeployments()],
            'findDeployment' => [self::PROCESS, 'GET', '/service/repository/deployments/dep-1', static fn (FlowableClient $c) => $c->findDeployment('dep-1')],
            'createDeployment' => [self::PROCESS, 'POST', '/service/repository/deployments', static fn (FlowableClient $c) => $c->createDeployment('order.bpmn20.xml', '<x/>')],
            'deleteDeployment' => [self::PROCESS, 'DELETE', '/service/repository/deployments/dep-1', static fn (FlowableClient $c) => $c->deleteDeployment('dep-1')],
            'listProcessDefinitions' => [self::PROCESS, 'GET', '/service/repository/process-definitions', static fn (FlowableClient $c) => $c->listProcessDefinitions()],
            'findProcessDefinition' => [self::PROCESS, 'GET', '/service/repository/process-definitions/order%3A1%3A7', static fn (FlowableClient $c) => $c->findProcessDefinition('order:1:7')],
            'listProcessInstances' => [self::PROCESS, 'GET', '/service/runtime/process-instances', static fn (FlowableClient $c) => $c->listProcessInstances()],
            'findProcessInstance' => [self::PROCESS, 'GET', '/service/runtime/process-instances/pi-1', static fn (FlowableClient $c) => $c->findProcessInstance('pi-1')],
            'startProcessInstance' => [self::PROCESS, 'POST', '/service/runtime/process-instances', static fn (FlowableClient $c) => $c->startProcessInstance(['processDefinitionKey' => 'order'])],
            'deleteProcessInstance' => [self::PROCESS, 'DELETE', '/service/runtime/process-instances/pi-1', static fn (FlowableClient $c) => $c->deleteProcessInstance('pi-1')],
            'listTasks' => [self::PROCESS, 'GET', '/service/runtime/tasks', static fn (FlowableClient $c) => $c->listTasks()],
            'findTask' => [self::PROCESS, 'GET', '/service/runtime/tasks/task-1', static fn (FlowableClient $c) => $c->findTask('task-1')],
            'completeTask' => [self::PROCESS, 'POST', '/service/runtime/tasks/task-1', static fn (FlowableClient $c) => $c->completeTask('task-1', ['action' => 'complete'])],
            'listExecutions' => [self::PROCESS, 'GET', '/service/runtime/executions', static fn (FlowableClient $c) => $c->listExecutions()],
            'findExecution' => [self::PROCESS, 'GET', '/service/runtime/executions/ex-1', static fn (FlowableClient $c) => $c->findExecution('ex-1')],
            'triggerExecution' => [self::PROCESS, 'PUT', '/service/runtime/executions/ex-1', static fn (FlowableClient $c) => $c->triggerExecution('ex-1', ['action' => 'trigger'])],
            'getTaskFormData' => [self::PROCESS, 'GET', '/service/form/form-data?taskId=task-1', static fn (FlowableClient $c) => $c->getTaskFormData('task-1')],
            'getTaskVariables' => [self::PROCESS, 'GET', '/service/runtime/tasks/task-1/variables', static fn (FlowableClient $c) => $c->getTaskVariables('task-1')],
            'getStartFormData' => [self::PROCESS, 'GET', '/service/form/form-data?processDefinitionId=order:1:7', static fn (FlowableClient $c) => $c->getStartFormData('order:1:7')],
            'listDeploymentResources' => [self::PROCESS, 'GET', '/service/repository/deployments/dep-1/resources', static fn (FlowableClient $c) => $c->listDeploymentResources('dep-1')],
            'getDeploymentResource' => [self::PROCESS, 'GET', '/service/repository/deployments/dep-1/resourcedata/order.bpmn20.xml', static fn (FlowableClient $c) => $c->getDeploymentResource('dep-1', 'order.bpmn20.xml')],
            'listHistoricProcessInstances' => [self::PROCESS, 'GET', '/service/history/historic-process-instances', static fn (FlowableClient $c) => $c->listHistoricProcessInstances()],
            'findHistoricProcessInstance' => [self::PROCESS, 'GET', '/service/history/historic-process-instances/pi-1', static fn (FlowableClient $c) => $c->findHistoricProcessInstance('pi-1')],
            'deleteHistoricProcessInstance' => [self::PROCESS, 'DELETE', '/service/history/historic-process-instances/pi-1', static fn (FlowableClient $c) => $c->deleteHistoricProcessInstance('pi-1')],
            'listHistoricTasks' => [self::PROCESS, 'GET', '/service/history/historic-task-instances', static fn (FlowableClient $c) => $c->listHistoricTasks()],
            'findHistoricTask' => [self::PROCESS, 'GET', '/service/history/historic-task-instances/task-1', static fn (FlowableClient $c) => $c->findHistoricTask('task-1')],
            'listHistoricVariables' => [self::PROCESS, 'GET', '/service/history/historic-variable-instances', static fn (FlowableClient $c) => $c->listHistoricVariables()],
            'listHistoricActivities' => [self::PROCESS, 'GET', '/service/history/historic-activity-instances', static fn (FlowableClient $c) => $c->listHistoricActivities()],
            'listJobs' => [self::PROCESS, 'GET', '/service/management/jobs', static fn (FlowableClient $c) => $c->listJobs()],
            'listTimerJobs' => [self::PROCESS, 'GET', '/service/management/timer-jobs', static fn (FlowableClient $c) => $c->listTimerJobs()],
            'listSuspendedJobs' => [self::PROCESS, 'GET', '/service/management/suspended-jobs', static fn (FlowableClient $c) => $c->listSuspendedJobs()],
            'listDeadLetterJobs' => [self::PROCESS, 'GET', '/service/management/deadletter-jobs', static fn (FlowableClient $c) => $c->listDeadLetterJobs()],
            'listHistoryJobs' => [self::PROCESS, 'GET', '/service/management/history-jobs', static fn (FlowableClient $c) => $c->listHistoryJobs()],
            'findJob' => [self::PROCESS, 'GET', '/service/management/jobs/job-1', static fn (FlowableClient $c) => $c->findJob('job-1')],
            'getJobExceptionStacktrace' => [self::PROCESS, 'GET', '/service/management/jobs/job-1/exception-stacktrace', static fn (FlowableClient $c) => $c->getJobExceptionStacktrace('job-1')],
            'executeTimerJob' => [self::PROCESS, 'POST', '/service/management/timer-jobs/job-1', static fn (FlowableClient $c) => $c->executeTimerJob('job-1')],

            // DMN engine
            'listDmnDeployments' => [self::DMN, 'GET', '/dmn-api/dmn-repository/deployments', static fn (FlowableClient $c) => $c->listDmnDeployments()],
            'findDmnDeployment' => [self::DMN, 'GET', '/dmn-api/dmn-repository/deployments/dep-1', static fn (FlowableClient $c) => $c->findDmnDeployment('dep-1')],
            'createDmnDeployment' => [self::DMN, 'POST', '/dmn-api/dmn-repository/deployments', static fn (FlowableClient $c) => $c->createDmnDeployment('rates.dmn', '<x/>')],
            'deleteDmnDeployment' => [self::DMN, 'DELETE', '/dmn-api/dmn-repository/deployments/dep-1', static fn (FlowableClient $c) => $c->deleteDmnDeployment('dep-1')],
            'listDecisions' => [self::DMN, 'GET', '/dmn-api/dmn-repository/decisions', static fn (FlowableClient $c) => $c->listDecisions()],
            'findDecision' => [self::DMN, 'GET', '/dmn-api/dmn-repository/decisions/dec-1', static fn (FlowableClient $c) => $c->findDecision('dec-1')],
            'executeDecision' => [self::DMN, 'POST', '/dmn-api/dmn-rule/execute', static fn (FlowableClient $c) => $c->executeDecision(['decisionKey' => 'rates'])],
            'executeDecisionSingleResult' => [self::DMN, 'POST', '/dmn-api/dmn-rule/execute/single-result', static fn (FlowableClient $c) => $c->executeDecisionSingleResult(['decisionKey' => 'rates'])],
            'listHistoricDecisionExecutions' => [self::DMN, 'GET', '/dmn-api/dmn-history/historic-decision-executions', static fn (FlowableClient $c) => $c->listHistoricDecisionExecutions()],
            'findHistoricDecisionExecution' => [self::DMN, 'GET', '/dmn-api/dmn-history/historic-decision-executions/hde-1', static fn (FlowableClient $c) => $c->findHistoricDecisionExecution('hde-1')],

            // Event Registry
            'listEventDeployments' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/deployments', static fn (FlowableClient $c) => $c->listEventDeployments()],
            'findEventDeployment' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/deployments/dep-1', static fn (FlowableClient $c) => $c->findEventDeployment('dep-1')],
            'createEventDeployment' => [self::EVENT, 'POST', '/event-registry-api/event-registry-repository/deployments', static fn (FlowableClient $c) => $c->createEventDeployment('order.event', '{}')],
            'deleteEventDeployment' => [self::EVENT, 'DELETE', '/event-registry-api/event-registry-repository/deployments/dep-1', static fn (FlowableClient $c) => $c->deleteEventDeployment('dep-1')],
            'listEventDefinitions' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/event-definitions', static fn (FlowableClient $c) => $c->listEventDefinitions()],
            'findEventDefinition' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/event-definitions/evt-1', static fn (FlowableClient $c) => $c->findEventDefinition('evt-1')],
            'listChannelDefinitions' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/channel-definitions', static fn (FlowableClient $c) => $c->listChannelDefinitions()],
            'findChannelDefinition' => [self::EVENT, 'GET', '/event-registry-api/event-registry-repository/channel-definitions/chn-1', static fn (FlowableClient $c) => $c->findChannelDefinition('chn-1')],
            'getEventRegistryEngineInfo' => [self::EVENT, 'GET', '/event-registry-api/event-registry-management/engine', static fn (FlowableClient $c) => $c->getEventRegistryEngineInfo()],
            'createEventInstance' => [self::EVENT, 'POST', '/event-registry-api/event-registry-runtime/event-instances', static fn (FlowableClient $c) => $c->createEventInstance(['eventDefinitionKey' => 'orderCreated'])],

            // External worker jobs
            'acquireExternalWorkerJobs' => [self::EXTERNAL, 'POST', '/external-job-api/acquire/jobs', static fn (FlowableClient $c) => $c->acquireExternalWorkerJobs(['topic' => 'orders', 'workerId' => 'worker-1'])],
            'completeExternalWorkerJob' => [self::EXTERNAL, 'POST', '/external-job-api/acquire/jobs/job-1/complete', static fn (FlowableClient $c) => $c->completeExternalWorkerJob('job-1', ['workerId' => 'worker-1'])],
            'failExternalWorkerJob' => [self::EXTERNAL, 'POST', '/external-job-api/acquire/jobs/job-1/fail', static fn (FlowableClient $c) => $c->failExternalWorkerJob('job-1', ['workerId' => 'worker-1'])],
            'bpmnErrorExternalWorkerJob' => [self::EXTERNAL, 'POST', '/external-job-api/acquire/jobs/job-1/bpmnError', static fn (FlowableClient $c) => $c->bpmnErrorExternalWorkerJob('job-1', ['workerId' => 'worker-1'])],
            'unacquireExternalWorkerJob' => [self::EXTERNAL, 'POST', '/external-job-api/unacquire/jobs/job-1', static fn (FlowableClient $c) => $c->unacquireExternalWorkerJob('job-1', ['workerId' => 'worker-1'])],
            'unacquireAllExternalWorkerJobs' => [self::EXTERNAL, 'POST', '/external-job-api/unacquire/jobs', static fn (FlowableClient $c) => $c->unacquireAllExternalWorkerJobs(['workerId' => 'worker-1'])],
            'listExternalWorkerJobs' => [self::EXTERNAL, 'GET', '/external-job-api/jobs', static fn (FlowableClient $c) => $c->listExternalWorkerJobs()],
            'findExternalWorkerJob' => [self::EXTERNAL, 'GET', '/external-job-api/jobs/job-1', static fn (FlowableClient $c) => $c->findExternalWorkerJob('job-1')],
        ];
    }

    /**
     * @return iterable<string, array{string, string, string, \Closure(FlowableClient): mixed}>
     */
    public static function requestMethods(): iterable
    {
        yield from self::calls();
    }

    #[DataProvider('requestMethods')]
    public function testRequestGoesToThePrefixOfItsEnginePart(string $prefix, string $httpMethod, string $path, \Closure $call): void
    {
        $call($this->client());

        self::assertCount(1, $this->requests);
        self::assertSame($httpMethod, $this->requests[0]['method']);
        self::assertSame(self::BASE_URL . $path, $this->requests[0]['url']);

        $relative = substr($this->requests[0]['url'], \strlen(self::BASE_URL));
        self::assertStringStartsWith($prefix . '/', $relative);
        foreach (self::PREFIXES as $other) {
            self::assertSame(
                $other === $prefix ? 1 : 0,
                substr_count($relative, $other . '/'),
                sprintf('prefix %s in %s', $other, $relative),
            );
        }
    }

    public function testTrailingSlashOfTheBaseUrlIsNotDoubled(): void
    {
        $this->client(self::BASE_URL . '/')->listTasks();
        $this->client(self::BASE_URL . '/')->acquireExternalWorkerJobs([]);

        self::assertSame(self::BASE_URL . '/service/runtime/tasks', $this->requests[0]['url']);
        self::assertSame(self::BASE_URL . '/external-job-api/acquire/jobs', $this->requests[1]['url']);
    }

    public function testExternalWorkerPayloadIsSentAsJson(): void
    {
        $this->client()->failExternalWorkerJob('job-1', ['workerId' => 'worker-1', 'errorMessage' => 'failed', 'retries' => 2]);

        self::assertSame('{"workerId":"worker-1","errorMessage":"failed","retries":2}', $this->requests[0]['body']);
    }

    public function testAcquireKeepsOnlyJobEntries(): void
    {
        $jobs = $this->client(self::BASE_URL, '[{"id": "job-1"}, "noise", 7, {"id": "job-2"}]')
            ->acquireExternalWorkerJobs(['topic' => 'orders']);

        self::assertSame([['id' => 'job-1'], ['id' => 'job-2']], $jobs);
    }

    /**
     * Guards the list above: a new public request method must be added to
     * {@see self::calls()} with the prefix of its engine part.
     */
    public function testEveryPublicRequestMethodIsListed(): void
    {
        $methods = [];
        foreach ((new \ReflectionClass(FlowableClient::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->isStatic() && !\in_array($method->getName(), self::NO_REQUEST_METHODS, true)) {
                $methods[] = $method->getName();
            }
        }
        sort($methods);
        $listed = array_keys(self::calls());
        sort($listed);

        self::assertSame($methods, $listed);
    }

    private function client(string $baseUrl = self::BASE_URL, string $responseBody = '{"id":"stub"}'): FlowableClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($responseBody): ResponseInterface {
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

            return new MockResponse($responseBody, ['response_headers' => ['Content-Type: application/json']]);
        });

        return new FlowableClient($http, $baseUrl, 'basic', 'u', 'p', null);
    }
}
