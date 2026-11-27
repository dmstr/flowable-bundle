<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:24 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Tests\Kernel\RecordingLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * {@see FlowableClient::getTaskVariables()}: the engine's list of
 * `{name, value, type, scope}` becomes a name => value map, a missing task
 * is an empty map, every other failure is rethrown.
 */
final class FlowableClientTaskVariablesTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';

    /** @var list<array{method: string, url: string}> */
    private array $requests = [];

    public function testRequestsTheTaskVariablesOfTheTask(): void
    {
        $this->client(new MockResponse('[]'))->getTaskVariables('task-1');

        self::assertSame(
            [['method' => 'GET', 'url' => self::BASE_URL . '/service/runtime/tasks/task-1/variables']],
            $this->requests,
        );
    }

    public function testListBecomesANameToValueMap(): void
    {
        $body = json_encode([
            ['name' => 'orderId', 'value' => 'A-1', 'type' => 'string', 'scope' => 'global'],
            ['name' => 'amount', 'value' => 12.5, 'type' => 'double', 'scope' => 'global'],
            ['name' => 'approved', 'value' => false, 'type' => 'boolean', 'scope' => 'local'],
            ['name' => 'lines', 'value' => ['a', 'b'], 'type' => 'json', 'scope' => 'local'],
            ['name' => 'customerName', 'value' => null, 'type' => 'null', 'scope' => 'local'],
            ['name' => 'noValue', 'type' => 'string', 'scope' => 'local'],
        ], \JSON_THROW_ON_ERROR);

        self::assertSame(
            ['orderId' => 'A-1', 'amount' => 12.5, 'approved' => false, 'lines' => ['a', 'b'], 'customerName' => null, 'noValue' => null],
            $this->client(new MockResponse($body))->getTaskVariables('task-1'),
        );
    }

    public function testEntriesWithoutANameAreSkipped(): void
    {
        $body = json_encode([
            ['value' => 'orphan', 'scope' => 'global'],
            ['name' => null, 'value' => 'null name'],
            'not an entry',
            42,
            ['name' => 'orderId', 'value' => 'A-1'],
        ], \JSON_THROW_ON_ERROR);

        self::assertSame(['orderId' => 'A-1'], $this->client(new MockResponse($body))->getTaskVariables('task-1'));
    }

    public function testLaterEntryWinsForADuplicateName(): void
    {
        $body = json_encode([
            ['name' => 'orderId', 'value' => 'global value', 'scope' => 'global'],
            ['name' => 'customerName', 'value' => 'Jane', 'scope' => 'global'],
            ['name' => 'orderId', 'value' => 'local value', 'scope' => 'local'],
        ], \JSON_THROW_ON_ERROR);

        self::assertSame(
            ['orderId' => 'local value', 'customerName' => 'Jane'],
            $this->client(new MockResponse($body))->getTaskVariables('task-1'),
        );
    }

    public function testEmptyBodyIsAnEmptyMap(): void
    {
        self::assertSame([], $this->client(new MockResponse(''))->getTaskVariables('task-1'));
    }

    public function testMissingTaskIsAnEmptyMap(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse('{"message": "Could not find a task"}', ['http_code' => 404]), $logger);

        self::assertSame([], $client->getTaskVariables('task-1'));
        self::assertCount(1, $this->requests);
        self::assertSame([], $logger->records(), 'a 404 is not logged');
    }

    public function testBackendErrorIsRethrownWithACorrelationId(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse('{"message": "boom"}', ['http_code' => 500]), $logger);

        try {
            $client->getTaskVariables('task-1');
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(502, $e->getStatusCode());
            self::assertNotNull($e->getCorrelationId());
            self::assertSame(sprintf(FlowableApiException::BACKEND_ERROR, $e->getCorrelationId()), $e->getMessage());
            $id = $e->getCorrelationId();
        }

        $records = $logger->records();
        self::assertCount(1, $records);
        self::assertSame($id, $records[0]['context']['correlationId']);
        self::assertSame(self::BASE_URL . '/service/runtime/tasks/task-1/variables', $records[0]['context']['url']);
    }

    public function testRejectedRequestIsRethrown(): void
    {
        try {
            $this->client(new MockResponse('{"message": "Bad request"}', ['http_code' => 400]))->getTaskVariables('task-1');
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertNull($e->getCorrelationId());
        }
    }

    public function testTransportErrorIsRethrownWithACorrelationId(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse('', ['error' => 'connection refused']), $logger);

        try {
            $client->getTaskVariables('task-1');
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(504, $e->getStatusCode());
            self::assertNotNull($e->getCorrelationId());
            self::assertSame(sprintf(FlowableApiException::UNREACHABLE, $e->getCorrelationId()), $e->getMessage());
            $id = $e->getCorrelationId();
        }

        $records = $logger->records();
        self::assertCount(1, $records);
        self::assertSame($id, $records[0]['context']['correlationId']);
        self::assertSame(504, $records[0]['context']['status']);
    }

    private function client(MockResponse $response, ?RecordingLogger $logger = null): FlowableClient
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): ResponseInterface {
            $this->requests[] = ['method' => $method, 'url' => $url];

            return $response;
        });

        return new FlowableClient($http, self::BASE_URL, 'basic', 'u', 'p', null, true, $logger ?? new RecordingLogger());
    }
}
