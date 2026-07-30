<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Client;

use Dmstr\Flowable\Exception\FlowableApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * HTTP implementation of the Flowable REST client.
 *
 * Conservative timeouts (connect/inactivity ~3s, total ~15s) keep a hung
 * engine from tying up PHP-FPM workers. Transport and HTTP failures are
 * translated into FlowableApiException (design D11).
 */
final class FlowableClient implements FlowableClientInterface
{
    private readonly HttpClientInterface $http;
    private readonly string $baseUrl;

    /**
     * @param 'basic'|'bearer' $authType
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
        string $authType,
        ?string $username,
        ?string $password,
        ?string $token,
        bool $verifySsl = true,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');

        $options = [
            'timeout' => 3.0,
            'max_duration' => 15.0,
            'verify_peer' => $verifySsl,
            'verify_host' => $verifySsl,
            'headers' => ['Accept' => 'application/json'],
        ];
        if ($authType === 'bearer') {
            $options['auth_bearer'] = (string) $token;
        } else {
            $options['auth_basic'] = [(string) $username, (string) $password];
        }

        $this->http = $httpClient->withOptions($options);
    }

    public function getEndpoint(): string
    {
        return $this->baseUrl;
    }

    public function getHealthInfo(): array
    {
        try {
            $info = $this->decode($this->request('GET', '/service/management/engine'));

            return ['status' => 'UP', 'reachable' => true, 'info' => $info];
        } catch (FlowableApiException $e) {
            // A non-504 status means the engine answered (e.g. auth rejected) —
            // it is reachable, just not healthy from our perspective.
            return [
                'status' => 'DOWN',
                'reachable' => $e->getStatusCode() !== 504,
                'detail' => $e->getMessage(),
            ];
        }
    }

    public function listDeployments(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/repository/deployments', $query));
    }

    public function findDeployment(string $id): ?array
    {
        return $this->findOne('/service/repository/deployments/'.rawurlencode($id));
    }

    public function createDeployment(string $filename, string $content, array $fields = []): array
    {
        [$body, $contentType] = $this->multipartBody($fields, 'file', $filename, $content);

        return $this->decode($this->requestRaw(
            'POST',
            '/service/repository/deployments',
            $body,
            ['Content-Type' => $contentType],
        ));
    }

    public function deleteDeployment(string $id, bool $cascade = false): void
    {
        $query = $cascade ? ['cascade' => 'true'] : [];
        $this->request('DELETE', '/service/repository/deployments/'.rawurlencode($id), $query);
    }

    public function listProcessDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/repository/process-definitions', $query));
    }

    public function findProcessDefinition(string $id): ?array
    {
        return $this->findOne('/service/repository/process-definitions/'.rawurlencode($id));
    }

    public function listProcessInstances(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/process-instances', $query));
    }

    public function findProcessInstance(string $id): ?array
    {
        return $this->findOne('/service/runtime/process-instances/'.rawurlencode($id));
    }

    public function startProcessInstance(array $payload): array
    {
        return $this->decode($this->request('POST', '/service/runtime/process-instances', [], $payload));
    }

    public function deleteProcessInstance(string $id): void
    {
        $this->request('DELETE', '/service/runtime/process-instances/'.rawurlencode($id));
    }

    public function listTasks(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/tasks', $query));
    }

    public function findTask(string $id): ?array
    {
        return $this->findOne('/service/runtime/tasks/'.rawurlencode($id));
    }

    public function completeTask(string $id, array $payload): ?array
    {
        return $this->decodeOrNull($this->request('POST', '/service/runtime/tasks/'.rawurlencode($id), [], $payload));
    }

    public function listExecutions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/executions', $query));
    }

    public function findExecution(string $id): ?array
    {
        return $this->findOne('/service/runtime/executions/'.rawurlencode($id));
    }

    public function triggerExecution(string $executionId, array $payload): ?array
    {
        return $this->decodeOrNull($this->request('PUT', '/service/runtime/executions/'.rawurlencode($executionId), [], $payload));
    }

    public function getTaskFormData(string $taskId): ?array
    {
        return $this->findOneQuery('/service/form/form-data', ['taskId' => $taskId]);
    }

    public function getTaskVariables(string $taskId): array
    {
        try {
            $raw = $this->decode($this->request('GET', '/service/runtime/tasks/'.rawurlencode($taskId).'/variables'));
        } catch (FlowableApiException $e) {
            if (404 === $e->getStatusCode()) {
                return [];
            }
            throw $e;
        }

        // Flowable returns a list of {name, value, type, scope}; flatten to a
        // name => value map. Later scopes (local) win over earlier (global),
        // matching the engine's own variable resolution order.
        $variables = [];
        foreach ($raw as $entry) {
            if (\is_array($entry) && isset($entry['name'])) {
                $variables[(string) $entry['name']] = $entry['value'] ?? null;
            }
        }

        return $variables;
    }

    public function getStartFormData(string $processDefinitionId): ?array
    {
        return $this->findOneQuery('/service/form/form-data', ['processDefinitionId' => $processDefinitionId]);
    }

    public function listDeploymentResources(string $deploymentId): array
    {
        $data = $this->decode($this->request('GET', '/service/repository/deployments/'.rawurlencode($deploymentId).'/resources'));

        return array_values(array_filter($data, 'is_array'));
    }

    public function getDeploymentResource(string $deploymentId, string $resourceId): ?string
    {
        $path = '/service/repository/deployments/'.rawurlencode($deploymentId).'/resourcedata/'.rawurlencode($resourceId);
        try {
            return $this->request('GET', $path)->getContent(false);
        } catch (FlowableApiException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }
            throw $e;
        } catch (HttpClientExceptionInterface $e) {
            throw FlowableApiException::unreachable($e->getMessage());
        }
    }

    public function listHistoricProcessInstances(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-process-instances', $query));
    }

    public function findHistoricProcessInstance(string $id): ?array
    {
        return $this->findOne('/service/history/historic-process-instances/'.rawurlencode($id));
    }

    public function listHistoricTasks(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-task-instances', $query));
    }

    public function findHistoricTask(string $id): ?array
    {
        return $this->findOne('/service/history/historic-task-instances/'.rawurlencode($id));
    }

    public function listHistoricVariables(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-variable-instances', $query));
    }

    public function listHistoricActivities(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-activity-instances', $query));
    }

    public function listDmnDeployments(array $query = []): array
    {
        return $this->decode($this->request('GET', '/dmn-api/dmn-repository/deployments', $query));
    }

    public function findDmnDeployment(string $id): ?array
    {
        return $this->findOne('/dmn-api/dmn-repository/deployments/'.rawurlencode($id));
    }

    public function createDmnDeployment(string $filename, string $content, array $fields = []): array
    {
        [$body, $contentType] = $this->multipartBody($fields, 'file', $filename, $content);

        return $this->decode($this->requestRaw(
            'POST',
            '/dmn-api/dmn-repository/deployments',
            $body,
            ['Content-Type' => $contentType],
        ));
    }

    public function deleteDmnDeployment(string $id): void
    {
        $this->request('DELETE', '/dmn-api/dmn-repository/deployments/'.rawurlencode($id));
    }

    public function listDecisions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/dmn-api/dmn-repository/decisions', $query));
    }

    public function findDecision(string $id): ?array
    {
        return $this->findOne('/dmn-api/dmn-repository/decisions/'.rawurlencode($id));
    }

    public function executeDecision(array $payload): array
    {
        return $this->decode($this->request('POST', '/dmn-api/dmn-rule/execute', [], $payload));
    }

    public function executeDecisionSingleResult(array $payload): array
    {
        return $this->decode($this->request('POST', '/dmn-api/dmn-rule/execute/single-result', [], $payload));
    }

    public function listHistoricDecisionExecutions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/dmn-api/dmn-history/historic-decision-executions', $query));
    }

    public function findHistoricDecisionExecution(string $id): ?array
    {
        return $this->findOne('/dmn-api/dmn-history/historic-decision-executions/'.rawurlencode($id));
    }

    public function listEventDeployments(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/deployments', $query));
    }

    public function findEventDeployment(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/deployments/'.rawurlencode($id));
    }

    public function createEventDeployment(string $filename, string $content, array $query = []): array
    {
        [$body, $contentType] = $this->multipartBody([], 'file', $filename, $content);

        // Metadata rides on the query string, not in multipart parts: this
        // endpoint parses getQueryString() itself, so a form field named
        // deploymentName/category would be silently dropped (see the interface).
        $path = '/event-registry-api/event-registry-repository/deployments';
        if ($query !== []) {
            $path .= '?'.http_build_query($query);
        }

        return $this->decode($this->requestRaw('POST', $path, $body, ['Content-Type' => $contentType]));
    }

    public function deleteEventDeployment(string $id): void
    {
        $this->request('DELETE', '/event-registry-api/event-registry-repository/deployments/'.rawurlencode($id));
    }

    public function listEventDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/event-definitions', $query));
    }

    public function findEventDefinition(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/event-definitions/'.rawurlencode($id));
    }

    public function listChannelDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/channel-definitions', $query));
    }

    public function findChannelDefinition(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/channel-definitions/'.rawurlencode($id));
    }

    public function getEventRegistryEngineInfo(): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-management/engine'));
    }

    public function createEventInstance(array $payload): void
    {
        // The engine answers 204 No Content — there is no body to decode.
        $this->request('POST', '/event-registry-api/event-registry-runtime/event-instances', [], $payload);
    }

    /**
     * The engine's five job collections. They share one response shape but are
     * strictly separate stores: an id is only resolvable under its own kind.
     * Declared next to its methods to keep this feature's diff self-contained.
     */
    private const JOB_COLLECTIONS = [
        'async' => '/service/management/jobs',
        'timer' => '/service/management/timer-jobs',
        'suspended' => '/service/management/suspended-jobs',
        'deadletter' => '/service/management/deadletter-jobs',
        'history' => '/service/management/history-jobs',
    ];

    public function acquireExternalWorkerJobs(array $payload): array
    {
        // This endpoint answers with a bare JSON array, so decode() yields a
        // list rather than the usual envelope; drop anything non-array so
        // callers can rely on list<array>.
        $jobs = $this->decode($this->request('POST', '/external-job-api/acquire/jobs', [], $payload));

        return array_values(array_filter($jobs, 'is_array'));
    }

    public function completeExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/acquire/jobs/'.rawurlencode($id).'/complete', [], $payload);
    }

    public function failExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/acquire/jobs/'.rawurlencode($id).'/fail', [], $payload);
    }

    public function bpmnErrorExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/acquire/jobs/'.rawurlencode($id).'/bpmnError', [], $payload);
    }

    public function unacquireExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/unacquire/jobs/'.rawurlencode($id), [], $payload);
    }

    public function unacquireAllExternalWorkerJobs(array $payload): void
    {
        $this->request('POST', '/external-job-api/unacquire/jobs', [], $payload);
    }

    public function listExternalWorkerJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', '/external-job-api/jobs', $query));
    }

    public function findExternalWorkerJob(string $id): ?array
    {
        return $this->findOne('/external-job-api/jobs/'.rawurlencode($id));
    }

    public function listJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', self::JOB_COLLECTIONS['async'], $query));
    }

    public function listTimerJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', self::JOB_COLLECTIONS['timer'], $query));
    }

    public function listSuspendedJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', self::JOB_COLLECTIONS['suspended'], $query));
    }

    public function listDeadLetterJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', self::JOB_COLLECTIONS['deadletter'], $query));
    }

    public function listHistoryJobs(array $query = []): array
    {
        return $this->decode($this->request('GET', self::JOB_COLLECTIONS['history'], $query));
    }

    public function findJob(string $id, string $kind = 'async'): ?array
    {
        return $this->findOne($this->jobCollection($kind).'/'.rawurlencode($id));
    }

    public function getJobExceptionStacktrace(string $id, string $kind = 'async'): ?string
    {
        // History jobs have no stacktrace endpoint (the engine answers 500 "no
        // endpoint"), so short-circuit instead of provoking that error.
        if ($kind === 'history') {
            return null;
        }

        $path = $this->jobCollection($kind).'/'.rawurlencode($id).'/exception-stacktrace';
        try {
            // text/plain, not JSON — read the body verbatim.
            return $this->request('GET', $path)->getContent(false);
        } catch (FlowableApiException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }
            throw $e;
        } catch (HttpClientExceptionInterface $e) {
            throw FlowableApiException::unreachable($e->getMessage());
        }
    }

    private function jobCollection(string $kind): string
    {
        return self::JOB_COLLECTIONS[$kind]
            ?? throw new \InvalidArgumentException(sprintf(
                'Unknown Flowable job kind "%s". Allowed: %s.',
                $kind,
                implode(', ', array_keys(self::JOB_COLLECTIONS)),
            ));
    }

    /**
     * @param array<string,scalar> $query
     * @param array<string,mixed>|null $json
     */
    private function request(string $method, string $path, array $query = [], ?array $json = null): ResponseInterface
    {
        $options = [];
        if ($query !== []) {
            $options['query'] = $query;
        }
        if ($json !== null) {
            $options['json'] = $json;
        }

        try {
            $response = $this->http->request($method, $this->baseUrl.$path, $options);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw FlowableApiException::unreachable($e->getMessage());
        }

        if ($status >= 400) {
            throw FlowableApiException::fromUpstreamStatus($status, $this->messageFrom($response));
        }

        return $response;
    }

    /**
     * Send a pre-encoded raw body (e.g. multipart) with explicit headers.
     *
     * @param array<string,string> $headers
     */
    private function requestRaw(string $method, string $path, string $body, array $headers): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $this->baseUrl.$path, [
                'headers' => $headers,
                'body' => $body,
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw FlowableApiException::unreachable($e->getMessage());
        }

        if ($status >= 400) {
            throw FlowableApiException::fromUpstreamStatus($status, $this->messageFrom($response));
        }

        return $response;
    }

    /**
     * Build a multipart/form-data body without pulling in symfony/mime, so the
     * bundle stays dependency-free. Scalar form fields are emitted first, the
     * file part last; the file extension is preserved (Flowable derives the
     * resource type from it).
     *
     * @param array<string,string> $fields
     * @return array{0:string,1:string} [body, Content-Type]
     */
    private function multipartBody(array $fields, string $fileField, string $filename, string $content): array
    {
        $boundary = 'za7flowable'.bin2hex(random_bytes(16));
        $eol = "\r\n";
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '--'.$boundary.$eol
                .'Content-Disposition: form-data; name="'.$name.'"'.$eol.$eol
                .$value.$eol;
        }

        $body .= '--'.$boundary.$eol
            .'Content-Disposition: form-data; name="'.$fileField.'"; filename="'.$filename.'"'.$eol
            .'Content-Type: application/octet-stream'.$eol.$eol
            .$content.$eol
            .'--'.$boundary.'--'.$eol;

        return [$body, 'multipart/form-data; boundary='.$boundary];
    }

    private function findOne(string $path): ?array
    {
        try {
            return $this->decode($this->request('GET', $path));
        } catch (FlowableApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * @param array<string,scalar> $query
     * @return array<string,mixed>|null
     */
    private function findOneQuery(string $path, array $query): ?array
    {
        try {
            return $this->decode($this->request('GET', $path, $query));
        } catch (FlowableApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function decode(ResponseInterface $response): array
    {
        try {
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface $e) {
            throw FlowableApiException::unreachable($e->getMessage());
        }

        if (trim($content) === '') {
            return [];
        }
        $data = json_decode($content, true);

        return \is_array($data) ? $data : [];
    }

    /** @return array<string,mixed>|null */
    private function decodeOrNull(ResponseInterface $response): ?array
    {
        $data = $this->decode($response);

        return $data === [] ? null : $data;
    }

    private function messageFrom(ResponseInterface $response): string
    {
        try {
            $content = $response->getContent(false);
        } catch (HttpClientExceptionInterface) {
            return 'no response body';
        }
        $data = json_decode($content, true);
        if (\is_array($data)) {
            return (string) ($data['message'] ?? $data['exception'] ?? $content);
        }

        return $content !== '' ? $content : 'no response body';
    }
}
