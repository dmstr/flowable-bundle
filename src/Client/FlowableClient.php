<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Client;

use Dmstr\Flowable\Exception\FlowableApiException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
 *
 * A failure that becomes a 502 or 504 reaches the caller only as a generic
 * message with a correlation id; what the engine or the transport said
 * (upstream status, engine message, body excerpt, URL, exception) is logged
 * once, as {@see self::LOG_UPSTREAM_ERROR} (level error) on the `flowable`
 * channel, with the same id.
 */
final class FlowableClient implements FlowableClientInterface
{
    /** Message of the error-log entry behind every 502 and 504 */
    public const string LOG_UPSTREAM_ERROR = 'flowable.upstream_error';

    /** Characters of the engine message and body kept in the log entry */
    private const int LOG_EXCERPT_LENGTH = 2000;

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
        private readonly LoggerInterface $flowableLogger = new NullLogger(),
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
            // it is reachable, just not healthy from our perspective. The
            // message is the generic one; the details are in the log.
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
        return $this->findOne('/service/repository/deployments/'.self::idSegment($id));
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
        $this->request('DELETE', '/service/repository/deployments/'.self::idSegment($id), $query);
    }

    public function listProcessDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/repository/process-definitions', $query));
    }

    public function findProcessDefinition(string $id): ?array
    {
        return $this->findOne('/service/repository/process-definitions/'.self::idSegment($id));
    }

    public function listProcessInstances(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/process-instances', $query));
    }

    public function findProcessInstance(string $id): ?array
    {
        return $this->findOne('/service/runtime/process-instances/'.self::idSegment($id));
    }

    public function startProcessInstance(array $payload): array
    {
        return $this->decode($this->request('POST', '/service/runtime/process-instances', [], $payload));
    }

    public function deleteProcessInstance(string $id): void
    {
        $this->request('DELETE', '/service/runtime/process-instances/'.self::idSegment($id));
    }

    public function listTasks(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/tasks', $query));
    }

    public function findTask(string $id): ?array
    {
        return $this->findOne('/service/runtime/tasks/'.self::idSegment($id));
    }

    public function completeTask(string $id, array $payload): ?array
    {
        return $this->decodeOrNull($this->request('POST', '/service/runtime/tasks/'.self::idSegment($id), [], $payload));
    }

    public function listExecutions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/runtime/executions', $query));
    }

    public function findExecution(string $id): ?array
    {
        return $this->findOne('/service/runtime/executions/'.self::idSegment($id));
    }

    public function triggerExecution(string $executionId, array $payload): ?array
    {
        return $this->decodeOrNull($this->request('PUT', '/service/runtime/executions/'.self::idSegment($executionId), [], $payload));
    }

    public function getTaskFormData(string $taskId): ?array
    {
        return $this->findOneQuery('/service/form/form-data', ['taskId' => $taskId]);
    }

    public function getTaskVariables(string $taskId): array
    {
        try {
            $raw = $this->decode($this->request('GET', '/service/runtime/tasks/'.self::idSegment($taskId).'/variables'));
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
        $data = $this->decode($this->request('GET', '/service/repository/deployments/'.self::idSegment($deploymentId).'/resources'));

        return array_values(array_filter($data, 'is_array'));
    }

    public function getDeploymentResource(string $deploymentId, string $resourceId): ?string
    {
        $path = '/service/repository/deployments/'.self::idSegment($deploymentId).'/resourcedata/'.self::resourceSegment($resourceId);
        try {
            return $this->content($this->request('GET', $path));
        } catch (FlowableApiException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }
            throw $e;
        }
    }

    public function listHistoricProcessInstances(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-process-instances', $query));
    }

    public function findHistoricProcessInstance(string $id): ?array
    {
        return $this->findOne('/service/history/historic-process-instances/'.self::idSegment($id));
    }

    public function deleteHistoricProcessInstance(string $id): void
    {
        $this->request('DELETE', '/service/history/historic-process-instances/'.self::idSegment($id));
    }

    public function listHistoricTasks(array $query = []): array
    {
        return $this->decode($this->request('GET', '/service/history/historic-task-instances', $query));
    }

    public function findHistoricTask(string $id): ?array
    {
        return $this->findOne('/service/history/historic-task-instances/'.self::idSegment($id));
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
        return $this->findOne('/dmn-api/dmn-repository/deployments/'.self::idSegment($id));
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
        $this->request('DELETE', '/dmn-api/dmn-repository/deployments/'.self::idSegment($id));
    }

    public function listDecisions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/dmn-api/dmn-repository/decisions', $query));
    }

    public function findDecision(string $id): ?array
    {
        return $this->findOne('/dmn-api/dmn-repository/decisions/'.self::idSegment($id));
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
        return $this->findOne('/dmn-api/dmn-history/historic-decision-executions/'.self::idSegment($id));
    }

    public function listEventDeployments(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/deployments', $query));
    }

    public function findEventDeployment(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/deployments/'.self::idSegment($id));
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
        $this->request('DELETE', '/event-registry-api/event-registry-repository/deployments/'.self::idSegment($id));
    }

    public function listEventDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/event-definitions', $query));
    }

    public function findEventDefinition(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/event-definitions/'.self::idSegment($id));
    }

    public function listChannelDefinitions(array $query = []): array
    {
        return $this->decode($this->request('GET', '/event-registry-api/event-registry-repository/channel-definitions', $query));
    }

    public function findChannelDefinition(string $id): ?array
    {
        return $this->findOne('/event-registry-api/event-registry-repository/channel-definitions/'.self::idSegment($id));
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
        $this->request('POST', '/external-job-api/acquire/jobs/'.self::idSegment($id).'/complete', [], $payload);
    }

    public function failExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/acquire/jobs/'.self::idSegment($id).'/fail', [], $payload);
    }

    public function bpmnErrorExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/acquire/jobs/'.self::idSegment($id).'/bpmnError', [], $payload);
    }

    public function unacquireExternalWorkerJob(string $id, array $payload): void
    {
        $this->request('POST', '/external-job-api/unacquire/jobs/'.self::idSegment($id), [], $payload);
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
        return $this->findOne('/external-job-api/jobs/'.self::idSegment($id));
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
        return $this->findOne($this->jobCollection($kind).'/'.self::idSegment($id));
    }

    public function getJobExceptionStacktrace(string $id, string $kind = 'async'): ?string
    {
        // History jobs have no stacktrace endpoint (the engine answers 500 "no
        // endpoint"), so short-circuit instead of provoking that error.
        if ($kind === 'history') {
            return null;
        }

        $path = $this->jobCollection($kind).'/'.self::idSegment($id).'/exception-stacktrace';
        try {
            // text/plain, not JSON — read the body verbatim.
            return $this->content($this->request('GET', $path));
        } catch (FlowableApiException $e) {
            if (404 === $e->getStatusCode()) {
                return null;
            }
            throw $e;
        }
    }

    public function executeTimerJob(string $id): void
    {
        // The engine answers 204 No Content — there is no body to decode.
        $this->request('POST', $this->jobCollection('timer').'/'.self::idSegment($id), [], ['action' => 'move']);
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
     * The one rule for every id that becomes a segment of an engine path:
     * a letter or digit, then letters, digits, ":", ".", "_" or "-". This
     * rejects the empty string, dot segments, "/", "%" and any non-ASCII
     * character before a request is sent, for every caller (HTTP, MCP, CLI).
     *
     * @throws BadRequestHttpException when the id does not match the rule
     */
    private static function idSegment(string $id): string
    {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9:._-]*\z/', $id) !== 1) {
            throw new BadRequestHttpException(
                'Invalid Flowable id: expected a letter or digit followed by letters, digits, ":", ".", "_" or "-".',
            );
        }

        return rawurlencode($id);
    }

    /**
     * A deployment resource name comes from engine data (e.g.
     * "<formKey>.schema.json", and a formKey may contain "/" or spaces), so
     * only dot segments are rejected; the name is sent as one encoded segment.
     *
     * @throws BadRequestHttpException when the name contains a dot segment
     */
    private static function resourceSegment(string $resourceId): string
    {
        foreach (explode('/', $resourceId) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new BadRequestHttpException('Invalid Flowable resource name: "." and ".." segments are not allowed.');
            }
        }

        return rawurlencode($resourceId);
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
            throw $this->unreachable($e, $method, $this->baseUrl.$path);
        }

        if ($status >= 400) {
            throw $this->upstreamError($response, $status, $method, $this->baseUrl.$path);
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
            throw $this->unreachable($e, $method, $this->baseUrl.$path);
        }

        if ($status >= 400) {
            throw $this->upstreamError($response, $status, $method, $this->baseUrl.$path);
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

        // Escape the file name as browsers do (WHATWG multipart/form-data), so
        // a quote, CR or LF in it cannot end the parameter or the header.
        $filename = strtr($filename, ['"' => '%22', "\r" => '%0D', "\n" => '%0A']);

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
        $content = $this->content($response);

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

    /**
     * The body of a successful response; a transport failure while reading
     * it (e.g. a timeout mid-body) is a 504 like one before the status.
     */
    private function content(ResponseInterface $response): string
    {
        try {
            return $response->getContent(false);
        } catch (HttpClientExceptionInterface $e) {
            throw $this->unreachable(
                $e,
                (string) $response->getInfo('http_method'),
                (string) $response->getInfo('url'),
            );
        }
    }

    private function upstreamError(ResponseInterface $response, int $status, string $method, string $url): FlowableApiException
    {
        try {
            $body = $response->getContent(false);
        } catch (HttpClientExceptionInterface) {
            $body = '';
        }
        $engineMessage = self::messageFrom($body);

        return $this->logged(FlowableApiException::fromUpstreamStatus($status, $engineMessage), [
            'upstreamStatus' => $status,
            'method' => $method,
            'url' => $url,
            'engineMessage' => $engineMessage === null ? null : mb_substr($engineMessage, 0, self::LOG_EXCERPT_LENGTH),
            'body' => mb_substr($body, 0, self::LOG_EXCERPT_LENGTH),
        ]);
    }

    private function unreachable(HttpClientExceptionInterface $e, string $method, string $url): FlowableApiException
    {
        return $this->logged(FlowableApiException::unreachable(), [
            'method' => $method,
            'url' => $url,
            'exception' => $e::class,
            'exceptionMessage' => $e->getMessage(),
        ]);
    }

    /**
     * Writes the error-log entry of an exception that carries a correlation
     * id (502, 504); a 404 or 422 hides nothing and is not logged.
     *
     * @param array<string,mixed> $details
     */
    private function logged(FlowableApiException $exception, array $details): FlowableApiException
    {
        $correlationId = $exception->getCorrelationId();
        if ($correlationId !== null) {
            $this->flowableLogger->error(self::LOG_UPSTREAM_ERROR, [
                'correlationId' => $correlationId,
                'status' => $exception->getStatusCode(),
            ] + $details);
        }

        return $exception;
    }

    /**
     * The message of a Flowable error JSON (`message`, else `exception`), or
     * null when the body is no JSON object or carries no non-empty string
     * there. The raw body is never the message.
     */
    private static function messageFrom(string $body): ?string
    {
        $data = json_decode($body, true);
        if (!\is_array($data)) {
            return null;
        }
        foreach (['message', 'exception'] as $key) {
            if (\is_string($data[$key] ?? null) && trim($data[$key]) !== '') {
                return $data[$key];
            }
        }

        return null;
    }
}
// - revised 2026-10-08 (executeTimerJob for the MCP timer tool)
// - revised 2026-11-26 (id rule for engine paths, escaped multipart file name)
// - revised 2026-11-26 (generic 502/504 messages with a correlation id, details in the flowable log)
