<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:16:24 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Exception\FlowableApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The request {@see FlowableClient::createEventDeployment()} sends for an
 * event or channel definition: `POST` to the Event Registry deployments,
 * metadata on the query string, and a multipart body with exactly one part,
 * the file. The HTTP-level upload (suffix rule, default deployment name) is
 * covered in tests/Http; this pins the shape the client builds itself.
 */
final class FlowableClientEventDeploymentRequestTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';
    private const string DEPLOYMENTS = self::BASE_URL . '/event-registry-api/event-registry-repository/deployments';

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string}> */
    private array $requests = [];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function definitions(): iterable
    {
        yield 'event definition' => ['orderCreated.event', '{"key":"orderCreated","payload":[{"name":"orderId","type":"string"}]}'];
        yield 'channel definition' => ['orders.channel', '{"key":"orders","channelType":"inbound","type":"kafka"}'];
    }

    #[DataProvider('definitions')]
    public function testUploadIsOneMultipartFilePart(string $filename, string $content): void
    {
        $this->client()->createEventDeployment($filename, $content);

        self::assertCount(1, $this->requests);
        $request = $this->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame(self::DEPLOYMENTS, $request['url'], 'no query string without metadata');

        self::assertSame(1, preg_match('/\Amultipart\/form-data; boundary=(\S+)\z/', $request['headers']['content-type'] ?? '', $match));
        $boundary = $match[1];

        self::assertSame(
            '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="file"; filename="' . $filename . '"' . "\r\n"
            . "Content-Type: application/octet-stream\r\n"
            . "\r\n"
            . $content . "\r\n"
            . '--' . $boundary . "--\r\n",
            $request['body'],
        );
    }

    public function testMetadataRidesOnTheQueryStringOnly(): void
    {
        $this->client()->createEventDeployment('orderCreated.event', '{}', [
            'deploymentName' => 'order events',
            'category' => 'sales&returns',
            'tenantId' => 'tenant-1',
        ]);

        $request = $this->requests[0];
        self::assertSame(
            self::DEPLOYMENTS . '?deploymentName=order+events&category=sales%26returns&tenantId=tenant-1',
            $request['url'],
        );
        self::assertSame(1, substr_count($request['body'], 'Content-Disposition:'), 'metadata is no multipart part');
        self::assertStringNotContainsString('deploymentName', $request['body']);
        self::assertStringNotContainsString('tenant-1', $request['body']);
    }

    public function testBoundaryIsFreshPerRequestAndTheContentVerbatim(): void
    {
        $content = "line 1\r\n--not-a-boundary\r\nline 3";
        $this->client()->createEventDeployment('a.event', $content);
        $this->client()->createEventDeployment('a.event', $content);

        $first = $this->requests[0]['headers']['content-type'];
        $second = $this->requests[1]['headers']['content-type'];
        self::assertNotSame($first, $second);
        self::assertStringContainsString("\r\n\r\n" . $content . "\r\n--", $this->requests[0]['body'], 'content is sent verbatim');
    }

    public function testBinaryContentIsSentUnchanged(): void
    {
        $content = "\x00\xff\xfe{\"k\":\"\u{00e9}\"}\n";
        $this->client()->createEventDeployment('a.event', $content);

        self::assertStringContainsString("\r\n\r\n" . $content . "\r\n--", $this->requests[0]['body']);
    }

    public function testAuthAndAcceptHeadersAreSent(): void
    {
        $this->client()->createEventDeployment('a.event', '{}');

        $headers = $this->requests[0]['headers'];
        self::assertSame('application/json', $headers['accept'] ?? null);
        self::assertSame('Basic ' . base64_encode('u:p'), $headers['authorization'] ?? null);
    }

    public function testEngineAnswerIsDecoded(): void
    {
        $deployment = $this->client('{"id":"dep-1","name":"orderCreated.event"}', 201)->createEventDeployment('orderCreated.event', '{}');

        self::assertSame(['id' => 'dep-1', 'name' => 'orderCreated.event'], $deployment);
    }

    public function testRejectedUploadIsAnException(): void
    {
        try {
            $this->client('{"message": "Invalid event definition"}', 400)->createEventDeployment('a.event', '{}');
            self::fail('No exception');
        } catch (FlowableApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('Flowable rejected the request: Invalid event definition', $e->getMessage());
        }
    }

    private function client(string $responseBody = '{"id":"stub"}', int $status = 201): FlowableClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($responseBody, $status): ResponseInterface {
            $headers = [];
            foreach ($options['headers'] ?? [] as $header) {
                [$name, $value] = explode(':', $header, 2);
                $headers[strtolower($name)] = trim($value);
            }
            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => \is_string($options['body'] ?? null) ? $options['body'] : '',
            ];

            return new MockResponse($responseBody, ['http_code' => $status, 'response_headers' => ['Content-Type: application/json']]);
        });

        return new FlowableClient($http, self::BASE_URL, 'basic', 'u', 'p', null);
    }
}
