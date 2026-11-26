<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stand-in for the Flowable REST API, used as the response callback of the
 * test kernel's `http_client` (a MockHttpClient).
 *
 * It answers with plausible Flowable JSON, decided by the request path only:
 *
 * - `GET` on a collection (`.../tasks`, `.../process-definitions`, ...) returns
 *   the list envelope `{data: [], total: 0, start: 0, size: 0}`, unless a
 *   test gave that collection rows with {@see self::withList()};
 * - `GET` on `.../variables`, `.../resources` returns a plain empty list;
 * - `GET` on the engine info returns name and version;
 * - any other `GET` is a single object `{id: <last path segment>, name: ...}`;
 * - `DELETE` returns 204;
 * - `POST` and `PUT` return `{id: 'stub'}` with 201 / 200.
 *
 * Every request is recorded in {@see self::$requests}, so a test can assert
 * that an allowed operation reached the engine and a denied one did not; the
 * string body of each request is kept at the same index in
 * {@see self::$bodies} (null when the body is not a string), e.g. for the
 * multipart body of a deployment, and its decoded query string in
 * {@see self::$queries}.
 *
 * A test gives one path its own JSON answer with {@see self::withResponse()},
 * e.g. the result variables of a DMN evaluation or the form data of a task.
 *
 * By default no status above 399 is ever produced. A test makes the engine
 * fail with {@see self::failWith()} (an HTTP error status and body) or
 * {@see self::failWithTransportError()} (the response errors before it has a
 * status, as a timeout or a refused connection does, so FlowableClient ends
 * in its TransportExceptionInterface catch blocks). Each failure applies to
 * every request or to those whose path contains a string and/or have a
 * method, for a number of requests or until {@see self::reset()}; the first
 * matching failure wins, requests matching none get the default answer.
 * `{url}` in a body or message is replaced by the requested URL.
 *
 * State lives in the instance: every test gets a fresh container and so a
 * fresh stub. It is deliberately not a ResetInterface service, because the
 * kernel resets those between two requests of one test.
 */
final class TestFlowableEngine
{
    /** The `base_url` of the `flowable` ApiConfiguration the tests store */
    public const string BASE_URL = 'http://flowable.invalid/flowable-rest';

    private const string COLLECTION_PATTERN = '#/(deployments|process-definitions|process-instances|tasks|executions'
        . '|historic-[a-z-]+|decisions|event-definitions|channel-definitions|jobs|timer-jobs|suspended-jobs'
        . '|deadletter-jobs|history-jobs)$#';

    /** A curl-like message for an idle timeout, see {@see self::failWithTimeout()} */
    public const string TIMEOUT_MESSAGE = 'Idle timeout reached for "{url}".';

    /** A curl-like message for a refused connection, see {@see self::failWithConnectionRefused()} */
    public const string CONNECTION_REFUSED_MESSAGE = 'Failed to connect to flowable.invalid port 80 after 0 ms: '
        . 'Could not connect to server for "{url}".';

    /** @var list<array{method: string, path: string}> */
    public array $requests = [];

    /** @var list<?string> the string body of the request at the same index of $requests */
    public array $bodies = [];

    /** @var list<array<string, mixed>> the decoded query of the request at the same index of $requests */
    public array $queries = [];

    /** @var array<string, list<array<string, mixed>>> rows per collection path suffix, see withList() */
    private array $lists = [];

    /** @var list<array{suffix: string, method: ?string, body: array<mixed>, status: int}> see withResponse() */
    private array $responses = [];

    /**
     * @var list<array{status: ?int, body: string, error: ?string, path: ?string, method: ?string, times: ?int}>
     */
    private array $failures = [];

    /**
     * Answers matching requests with the given status and body (Content-Type
     * `application/json` when the body is JSON, `text/plain` otherwise).
     *
     * @param string|null $pathContains only requests whose path contains it
     * @param string|null $method only requests with this method, e.g. 'POST'
     * @param int|null $times for this many matching requests, null: until reset()
     */
    public function failWith(
        int $status,
        string $body,
        ?string $pathContains = null,
        ?string $method = null,
        ?int $times = null,
    ): self {
        return $this->addFailure($status, $body, null, $pathContains, $method, $times);
    }

    /**
     * Matching requests fail at the transport level with the given message:
     * reading their status throws a TransportException.
     */
    public function failWithTransportError(
        string $message,
        ?string $pathContains = null,
        ?string $method = null,
        ?int $times = null,
    ): self {
        return $this->addFailure(null, '', $message, $pathContains, $method, $times);
    }

    public function failWithTimeout(?string $pathContains = null, ?string $method = null, ?int $times = null): self
    {
        return $this->failWithTransportError(self::TIMEOUT_MESSAGE, $pathContains, $method, $times);
    }

    public function failWithConnectionRefused(
        ?string $pathContains = null,
        ?string $method = null,
        ?int $times = null,
    ): self {
        return $this->failWithTransportError(self::CONNECTION_REFUSED_MESSAGE, $pathContains, $method, $times);
    }

    /**
     * `GET` on a collection whose path ends with $pathSuffix answers with
     * these rows as the engine would: sorted by the `sort` field in the
     * requested `order` (asc when absent; unsorted without `sort`), then
     * paged by `start` and `size`; `total` is the count of all rows. Other
     * query parameters (filters) are ignored.
     *
     * @param list<array<string, mixed>> $rows
     */
    public function withList(string $pathSuffix, array $rows): self
    {
        $this->lists[$pathSuffix] = $rows;

        return $this;
    }

    /**
     * Requests whose path ends with $pathSuffix (and, when given, have the
     * method) are answered with $body as JSON and $status, before the
     * default answer and the lists of {@see self::withList()}; a failure of
     * {@see self::failWith()} still wins. The first matching entry is used.
     *
     * @param array<mixed> $body
     */
    public function withResponse(string $pathSuffix, array $body, ?string $method = null, int $status = 200): self
    {
        $this->responses[] = [
            'suffix' => $pathSuffix,
            'method' => $method === null ? null : strtoupper($method),
            'body' => $body,
            'status' => $status,
        ];

        return $this;
    }

    /**
     * Forgets the configured failures, lists and responses and the recorded
     * requests.
     */
    public function reset(): void
    {
        $this->failures = [];
        $this->lists = [];
        $this->responses = [];
        $this->requests = [];
        $this->bodies = [];
        $this->queries = [];
    }

    /**
     * An error body in the shape the Flowable REST API uses, e.g.
     * `{"message": "Bad request", "exception": "..."}`; FlowableClient reads
     * `message` first, then `exception`.
     */
    public static function errorBody(string $message, ?string $exception = null): string
    {
        $body = ['message' => $message];
        if ($exception !== null) {
            $body['exception'] = $exception;
        }

        return json_encode($body, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $this->requests[] = ['method' => $method, 'path' => $path];
        $this->bodies[] = \is_string($options['body'] ?? null) ? $options['body'] : null;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->queries[] = $query;

        $failure = $this->takeFailure($method, $path);
        if ($failure !== null) {
            return self::failureResponse($failure, $url);
        }

        $headers = ['response_headers' => ['Content-Type: application/json']];

        foreach ($this->responses as $response) {
            if (($response['method'] === null || $response['method'] === $method) && str_ends_with($path, $response['suffix'])) {
                return new MockResponse(
                    json_encode($response['body'], JSON_THROW_ON_ERROR),
                    ['http_code' => $response['status']] + $headers,
                );
            }
        }

        if ($method === 'DELETE') {
            return new MockResponse('', ['http_code' => 204]);
        }
        if ($method === 'POST' || $method === 'PUT') {
            return new MockResponse(
                json_encode(['id' => 'stub'], JSON_THROW_ON_ERROR),
                ['http_code' => $method === 'POST' ? 201 : 200] + $headers,
            );
        }

        return new MockResponse(json_encode($this->read($path, $query), JSON_THROW_ON_ERROR), $headers);
    }

    private function addFailure(
        ?int $status,
        string $body,
        ?string $error,
        ?string $pathContains,
        ?string $method,
        ?int $times,
    ): self {
        if ($times !== null && $times < 1) {
            throw new \InvalidArgumentException('$times must be at least 1 or null.');
        }
        $this->failures[] = [
            'status' => $status,
            'body' => $body,
            'error' => $error,
            'path' => $pathContains,
            'method' => $method === null ? null : strtoupper($method),
            'times' => $times,
        ];

        return $this;
    }

    /**
     * The first failure matching the request, counted down and removed when
     * it is used up.
     *
     * @return array{status: ?int, body: string, error: ?string, path: ?string, method: ?string, times: ?int}|null
     */
    private function takeFailure(string $method, string $path): ?array
    {
        foreach ($this->failures as $index => $failure) {
            if ($failure['method'] !== null && $failure['method'] !== $method) {
                continue;
            }
            if ($failure['path'] !== null && !str_contains($path, $failure['path'])) {
                continue;
            }
            if ($failure['times'] !== null && --$this->failures[$index]['times'] === 0) {
                array_splice($this->failures, $index, 1);
            }

            return $failure;
        }

        return null;
    }

    /**
     * @param array{status: ?int, body: string, error: ?string, path: ?string, method: ?string, times: ?int} $failure
     */
    private static function failureResponse(array $failure, string $url): MockResponse
    {
        if ($failure['error'] !== null) {
            // MockResponse throws a TransportException with this message on
            // the first access to the response, as a curl response does
            return new MockResponse('', ['error' => str_replace('{url}', $url, $failure['error'])]);
        }

        $body = str_replace('{url}', $url, $failure['body']);
        $contentType = json_validate($body) ? 'application/json' : 'text/plain';

        return new MockResponse($body, [
            'http_code' => $failure['status'],
            'response_headers' => ['Content-Type: ' . $contentType],
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>|list<mixed>
     */
    private function read(string $path, array $query): array
    {
        foreach ($this->lists as $suffix => $rows) {
            if (str_ends_with($path, $suffix)) {
                return self::page($rows, $query);
            }
        }
        if (preg_match(self::COLLECTION_PATTERN, $path) === 1) {
            return ['data' => [], 'total' => 0, 'start' => 0, 'size' => 0];
        }
        if (preg_match('#/(variables|resources)$#', $path) === 1) {
            return [];
        }
        if (str_ends_with($path, '/engine')) {
            return ['name' => 'stub', 'version' => '7.0.0'];
        }

        return ['id' => basename($path), 'name' => 'stub'];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $query
     * @return array{data: list<array<string, mixed>>, total: int, start: int, size: int}
     */
    private static function page(array $rows, array $query): array
    {
        $sort = $query['sort'] ?? null;
        if (\is_string($sort)) {
            $direction = ($query['order'] ?? 'asc') === 'desc' ? -1 : 1;
            usort($rows, static fn (array $a, array $b): int => $direction * (($a[$sort] ?? null) <=> ($b[$sort] ?? null)));
        }
        $start = (int) ($query['start'] ?? 0);
        $data = \array_slice($rows, $start, isset($query['size']) ? (int) $query['size'] : null);

        return ['data' => $data, 'total' => \count($rows), 'start' => $start, 'size' => \count($data)];
    }
}
// - revised 2026-11-26 (failure mode: HTTP error status and transport errors)
// - revised 2026-11-26 (request bodies recorded)
// - revised 2026-11-26 (queries recorded, collections with rows that honour sort, order, start and size)
// - revised 2026-11-26 (own JSON answer per path with withResponse())
