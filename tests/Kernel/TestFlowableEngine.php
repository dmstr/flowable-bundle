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
 *   the list envelope `{data: [], total: 0, start: 0, size: 0}`;
 * - `GET` on `.../variables`, `.../resources` returns a plain empty list;
 * - `GET` on the engine info returns name and version;
 * - any other `GET` is a single object `{id: <last path segment>, name: ...}`;
 * - `DELETE` returns 204;
 * - `POST` and `PUT` return `{id: 'stub'}` with 201 / 200.
 *
 * Every request is recorded in {@see self::$requests}, so a test can assert
 * that an allowed operation reached the engine and a denied one did not.
 * No status above 399 is ever produced.
 */
final class TestFlowableEngine
{
    /** The `base_url` of the `flowable` ApiConfiguration the tests store */
    public const string BASE_URL = 'http://flowable.invalid/flowable-rest';

    private const string COLLECTION_PATTERN = '#/(deployments|process-definitions|process-instances|tasks|executions'
        . '|historic-[a-z-]+|decisions|event-definitions|channel-definitions|jobs|timer-jobs|suspended-jobs'
        . '|deadletter-jobs|history-jobs)$#';

    /** @var list<array{method: string, path: string}> */
    public array $requests = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $this->requests[] = ['method' => $method, 'path' => $path];

        $headers = ['response_headers' => ['Content-Type: application/json']];

        if ($method === 'DELETE') {
            return new MockResponse('', ['http_code' => 204]);
        }
        if ($method === 'POST' || $method === 'PUT') {
            return new MockResponse(
                json_encode(['id' => 'stub'], JSON_THROW_ON_ERROR),
                ['http_code' => $method === 'POST' ? 201 : 200] + $headers,
            );
        }

        return new MockResponse(json_encode($this->read($path), JSON_THROW_ON_ERROR), $headers);
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function read(string $path): array
    {
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
}
