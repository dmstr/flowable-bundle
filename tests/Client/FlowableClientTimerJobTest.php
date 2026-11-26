<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:02:34 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Exception\FlowableApiException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The one engine call behind the MCP tool flowable_system_execute_timer_job
 * ({@see FlowableClient::executeTimerJob()}), pinned against a simulated
 * engine: `POST <base>/service/management/timer-jobs/{id}` with the JSON
 * body `{"action": "move"}`, which moves the timer job to the executable
 * jobs. Whether the engine then runs it depends on its async executor; that
 * is not visible here and not verified against a real engine.
 */
final class FlowableClientTimerJobTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';

    /** @var list<array{method: string, url: string, contentType: ?string, body: mixed}> */
    private array $requests = [];

    public function testRunNowMovesTheTimerJobToTheExecutableJobs(): void
    {
        $this->client(new MockResponse('', ['http_code' => 204]))->executeTimerJob('timer-1');

        self::assertCount(1, $this->requests);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame(self::BASE_URL . '/service/management/timer-jobs/timer-1', $this->requests[0]['url']);
        self::assertSame('application/json', $this->requests[0]['contentType']);
        self::assertSame('{"action":"move"}', $this->requests[0]['body']);
    }

    public function testUnknownTimerJobIsNotFound(): void
    {
        try {
            $this->client(new MockResponse('{"message": "Could not find a timer job with id \'x\'."}', ['http_code' => 404]))
                ->executeTimerJob('x');
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame(FlowableApiException::NOT_FOUND, $e->getMessage());
        }
        self::assertCount(1, $this->requests);
    }

    private function client(MockResponse $response): FlowableClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($response): ResponseInterface {
            $contentType = null;
            foreach ($options['headers'] ?? [] as $header) {
                if (str_starts_with(strtolower($header), 'content-type:')) {
                    $contentType = trim(substr($header, \strlen('content-type:')));
                }
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'contentType' => $contentType, 'body' => $options['body'] ?? null];

            return $response;
        });

        return new FlowableClient($http, self::BASE_URL, 'basic', 'u', 'p', null);
    }
}
