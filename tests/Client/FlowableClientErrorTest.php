<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:38:26 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Tests\Kernel\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * How {@see FlowableClient} reads an engine error: the message of the error
 * JSON (`message`, else `exception`) and never the raw body, and one
 * error-log entry with the details behind every 502/504.
 */
final class FlowableClientErrorTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedBodies(): iterable
    {
        yield 'message' => ['{"message": "Bad request", "exception": "Variable x unknown"}', 'Flowable rejected the request: Bad request'];
        yield 'exception when message is missing' => ['{"exception": "Variable x unknown"}', 'Flowable rejected the request: Variable x unknown'];
        yield 'exception when message is empty' => ['{"message": "", "exception": "Variable x unknown"}', 'Flowable rejected the request: Variable x unknown'];
        yield 'empty body' => ['', FlowableApiException::REJECTED];
        yield 'not JSON' => ['<html>proxy at engine.internal</html>', FlowableApiException::REJECTED];
        yield 'JSON scalar' => ['"engine.internal"', FlowableApiException::REJECTED];
        yield 'JSON without message' => ['{"error": "engine.internal"}', FlowableApiException::REJECTED];
        yield 'message not a string' => ['{"message": ["engine.internal"]}', FlowableApiException::REJECTED];
    }

    #[DataProvider('rejectedBodies')]
    public function testRejectedRequestUsesTheErrorJsonMessageOnly(string $body, string $expected): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse($body, ['http_code' => 400]), $logger);

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame($expected, $e->getMessage());
        }
        self::assertSame([], $logger->records(), 'a 422 hides nothing and is not logged');
    }

    public function testBackendErrorIsLoggedWithTheDetails(): void
    {
        $logger = new RecordingLogger();
        $body = '{"message": "NPE in engine.internal", "exception": "java.lang.NullPointerException"}';
        $client = $this->client(new MockResponse($body, ['http_code' => 500]), $logger);

        try {
            $client->startProcessInstance(['processDefinitionKey' => 'p']);
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(502, $e->getStatusCode());
            self::assertStringNotContainsString('engine', $e->getMessage());
            $id = $e->getCorrelationId();
        }

        self::assertSame([[
            'level' => 'error',
            'message' => FlowableClient::LOG_UPSTREAM_ERROR,
            'context' => [
                'correlationId' => $id,
                'status' => 502,
                'upstreamStatus' => 500,
                'method' => 'POST',
                'url' => self::BASE_URL . '/service/runtime/process-instances',
                'engineMessage' => 'NPE in engine.internal',
                'body' => $body,
            ],
        ]], $logger->records());
    }

    public function testLoggedBodyIsAnExcerpt(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse(str_repeat('x', 10000), ['http_code' => 503]), $logger);

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException) {
        }

        $context = $logger->records()[0]['context'];
        self::assertSame(2000, \strlen($context['body']));
        self::assertNull($context['engineMessage']);
    }

    public function testTransportErrorWhileReadingTheBodyIsUnreachable(): void
    {
        $logger = new RecordingLogger();
        $body = (static function (): \Generator {
            yield '{"data": [';
            throw new \RuntimeException('Connection reset by engine.internal');
        })();
        $client = $this->client(new MockResponse($body), $logger);

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(504, $e->getStatusCode());
            self::assertSame(sprintf(FlowableApiException::UNREACHABLE, $e->getCorrelationId()), $e->getMessage());
            $id = $e->getCorrelationId();
        }

        $records = $logger->records();
        self::assertCount(1, $records);
        self::assertSame($id, $records[0]['context']['correlationId']);
        self::assertSame(504, $records[0]['context']['status']);
        self::assertSame('GET', $records[0]['context']['method']);
        self::assertStringStartsWith(self::BASE_URL . '/service/runtime/tasks', $records[0]['context']['url']);
        self::assertStringContainsString('engine.internal', $records[0]['context']['exceptionMessage']);
    }

    public function testNotFoundIsNotLogged(): void
    {
        $logger = new RecordingLogger();
        $client = $this->client(new MockResponse('{"message": "Not found"}', ['http_code' => 404]), $logger);

        self::assertNull($client->findTask('t1'));
        self::assertSame([], $logger->records());
    }

    public function testClientWithoutALoggerStillFailsGenerically(): void
    {
        // Positional construction without the logger, as before the parameter existed
        $client = new FlowableClient(new MockHttpClient(new MockResponse('', ['error' => 'refused by engine.internal'])), self::BASE_URL, 'basic', 'u', 'p', null);

        $this->expectException(FlowableApiException::class);
        $this->expectExceptionMessageMatches('/\AFlowable engine unreachable \(correlation id: [0-9a-f]{16}\)\.\z/');
        $client->listTasks();
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function engineStatuses(): iterable
    {
        yield 'engine 503' => [503, 502];
        yield 'engine 504' => [504, 502];
        yield 'engine 404' => [404, 404];
        yield 'engine 400' => [400, 422];
    }

    #[DataProvider('engineStatuses')]
    public function testEngineErrorCarriesTheUnmappedUpstreamStatus(int $upstream, int $status): void
    {
        $client = $this->client(new MockResponse('{"message": "engine.internal"}', ['http_code' => $upstream]), new RecordingLogger());

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame($status, $e->getStatusCode());
            self::assertSame($upstream, $e->getUpstreamStatus());
        }
    }

    public function testTransportErrorWhileSendingHasNoUpstreamStatus(): void
    {
        $client = $this->client(new MockResponse('', ['error' => 'timeout at engine.internal']), new RecordingLogger());

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(504, $e->getStatusCode());
            self::assertNull($e->getUpstreamStatus());
        }
    }

    public function testTransportErrorWhileReadingTheBodyHasNoUpstreamStatus(): void
    {
        $body = (static function (): \Generator {
            yield '{"data": [';
            throw new \RuntimeException('Connection reset by engine.internal');
        })();
        $client = $this->client(new MockResponse($body), new RecordingLogger());

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(504, $e->getStatusCode());
            self::assertNull($e->getUpstreamStatus());
        }
    }

    public function testErrorResponseWithAnUnreadableBodyKeepsTheEngineStatus(): void
    {
        $body = (static function (): \Generator {
            yield '{"message": ';
            throw new \RuntimeException('Connection reset by engine.internal');
        })();
        $client = $this->client(new MockResponse($body, ['http_code' => 503]), new RecordingLogger());

        try {
            $client->listTasks();
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(502, $e->getStatusCode());
            self::assertSame(503, $e->getUpstreamStatus());
        }
    }

    public function testDeploymentResourceNotFoundIsStillNull(): void
    {
        $client = $this->client(new MockResponse('{"message": "Not found"}', ['http_code' => 404]), new RecordingLogger());

        self::assertNull($client->getDeploymentResource('d1', 'r1'));
    }

    private function client(MockResponse $response, RecordingLogger $logger): FlowableClient
    {
        return new FlowableClient(new MockHttpClient($response), self::BASE_URL, 'basic', 'u', 'p', null, true, $logger);
    }
}
// - revised 2026-11-28 (getUpstreamStatus() offers the engine's HTTP status to PHP consumers)
