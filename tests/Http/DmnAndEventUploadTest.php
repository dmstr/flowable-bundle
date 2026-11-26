<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:02:34 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content and extension of the multipart uploads of the DMN repository
 * (POST /dmn_deployments/upload) and the event registry
 * (POST /event_deployments/upload), over HTTP:
 *
 *  - the uploaded bytes and the file name reach the engine unchanged, in the
 *    `file` part of the multipart body the bundle builds;
 *  - the DMN upload passes deployment-name, category and tenantId on as
 *    multipart fields (deployment-name defaults to the file name), the event
 *    upload as the query string (deploymentName, category, tenantId), as
 *    the event registry reads them only there;
 *  - an extension the repository does not accept is answered with 400
 *    before a request reaches the engine;
 *  - the engine's answer is mapped to the resource.
 *
 * Permissions, the file name rule and the full-suffix rule are covered by
 * OperationPermissionTest, UploadAndReadToolInputTest and UploadSuffixRuleTest.
 */
final class DmnAndEventUploadTest extends ApiTestCase
{
    /** Bytes that a re-encoding or a line-ending normalisation would change */
    private const string CONTENT = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\r\n<definitions name=\"Rabatt \xC3\xBCber 5 %\">\n\t<!-- \xE2\x82\xAC -->\n</definitions>\r\n";

    private const array ENGINE_DEPLOYMENT = [
        'id' => 'dep-7',
        'name' => 'rates',
        'deploymentTime' => '2026-11-26T12:00:00.000+00:00',
        'category' => 'pricing',
        'url' => 'http://flowable.invalid/engine-url',
        'parentDeploymentId' => 'dep-6',
        'tenantId' => 'acme',
    ];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // --- DMN ---------------------------------------------------------------------------

    public function testDmnUploadSendsTheFileAndTheFieldsUnchanged(): void
    {
        $this->engine()->withResponse('/dmn-api/dmn-repository/deployments', self::ENGINE_DEPLOYMENT, 'POST', 201);

        $response = $this->upload('/dmn_deployments/upload', 'rates.dmn', ['deployment-name' => 'rates', 'category' => 'pricing', 'tenantId' => 'acme']);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([['method' => 'POST', 'path' => '/flowable-rest/dmn-api/dmn-repository/deployments']], $this->engine()->requests);
        $parts = self::multipartParts((string) $this->engine()->bodies[0]);
        self::assertSame(['deployment-name' => 'rates', 'category' => 'pricing', 'tenantId' => 'acme'], $parts['fields']);
        self::assertSame('rates.dmn', $parts['filename']);
        self::assertSame(self::CONTENT, $parts['content']);
        self::assertSame([], $this->engine()->queries[0], 'the DMN upload sends no query string');

        $this->assertMappedDeployment(self::json($response), 'FlowDmnDeployment', '/dmn_deployments/dep-7');
    }

    public function testDmnUploadNamesTheDeploymentAfterTheFileByDefault(): void
    {
        $response = $this->upload('/dmn_deployments/upload', 'rates.dmn');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['deployment-name' => 'rates.dmn'], self::multipartParts((string) $this->engine()->bodies[0])['fields']);
    }

    public function testDmnUploadRejectsAProcessExtension(): void
    {
        $response = $this->upload('/dmn_deployments/upload', 'order.bpmn');

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('Unsupported resource extension ".bpmn". Allowed: dmn, bar, zip.', self::json($response)['detail'] ?? null);
        self::assertSame([], $this->engine()->requests);
    }

    // --- event registry ----------------------------------------------------------------

    public function testEventUploadSendsTheFileUnchangedAndTheFieldsAsQuery(): void
    {
        $this->engine()->withResponse('/event-registry-api/event-registry-repository/deployments', self::ENGINE_DEPLOYMENT, 'POST', 201);

        $response = $this->upload('/event_deployments/upload', 'order.event', ['deployment-name' => 'rates', 'category' => 'pricing', 'tenantId' => 'acme']);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([['method' => 'POST', 'path' => '/flowable-rest/event-registry-api/event-registry-repository/deployments']], $this->engine()->requests);
        self::assertSame(['deploymentName' => 'rates', 'category' => 'pricing', 'tenantId' => 'acme'], $this->engine()->queries[0]);
        $parts = self::multipartParts((string) $this->engine()->bodies[0]);
        self::assertSame([], $parts['fields'], 'the event registry reads no multipart fields');
        self::assertSame('order.event', $parts['filename']);
        self::assertSame(self::CONTENT, $parts['content']);

        $this->assertMappedDeployment(self::json($response), 'FlowEventDeployment', '/event_deployments/dep-7');
    }

    public function testEventUploadNamesTheDeploymentAfterTheFileByDefault(): void
    {
        $response = $this->upload('/event_deployments/upload', 'inbound.channel');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(['deploymentName' => 'inbound.channel'], $this->engine()->queries[0]);
        self::assertSame('inbound.channel', self::multipartParts((string) $this->engine()->bodies[0])['filename']);
    }

    public function testEventUploadRejectsADecisionTable(): void
    {
        $response = $this->upload('/event_deployments/upload', 'rates.dmn');

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            'Unsupported resource extension ".dmn". Allowed: event, channel (the event registry accepts no .bar/.zip bundles).',
            self::json($response)['detail'] ?? null,
        );
        self::assertSame([], $this->engine()->requests);
    }

    public function testEventUploadRejectsAnArchive(): void
    {
        $response = $this->upload('/event_deployments/upload', 'events.zip');

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringStartsWith('Unsupported resource extension ".zip".', (string) (self::json($response)['detail'] ?? ''));
        self::assertSame([], $this->engine()->requests);
    }

    // --- helpers -----------------------------------------------------------------------

    /**
     * @param array<string, mixed> $body
     */
    private function assertMappedDeployment(array $body, string $type, string $iri): void
    {
        self::assertSame($type, $body['@type'] ?? null);
        self::assertSame(self::FLOWABLE . $iri, $body['@id'] ?? null);
        foreach (['id', 'name', 'deploymentTime', 'category', 'parentDeploymentId', 'tenantId'] as $field) {
            self::assertSame(self::ENGINE_DEPLOYMENT[$field], $body[$field] ?? null, $field);
        }
    }

    /**
     * The form fields and the file part of a multipart body as FlowableClient
     * builds it (fields first, the file part last).
     *
     * @return array{fields: array<string, string>, filename: ?string, content: ?string}
     */
    private static function multipartParts(string $body): array
    {
        $boundary = substr($body, 2, (int) strpos($body, "\r\n") - 2);
        self::assertStringEndsWith("\r\n--" . $boundary . "--\r\n", $body);
        $parts = \array_slice(explode('--' . $boundary, $body), 1, -1);

        $result = ['fields' => [], 'filename' => null, 'content' => null];
        foreach ($parts as $part) {
            [$head, $content] = explode("\r\n\r\n", substr($part, 2), 2);
            $content = substr($content, 0, -2);
            self::assertSame(1, preg_match('/name="([^"]*)"(?:; filename="([^"]*)")?/', $head, $match), $head);
            if (isset($match[2])) {
                $result['filename'] = $match[2];
                $result['content'] = $content;
            } else {
                $result['fields'][$match[1]] = $content;
            }
        }

        return $result;
    }

    /**
     * @param array<string, string> $fields
     */
    private function upload(string $uri, string $name, array $fields = []): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        $this->tempFiles[] = $path;
        file_put_contents($path, self::CONTENT);

        $this->client->request(
            'POST',
            self::FLOWABLE . $uri,
            parameters: $fields,
            files: ['file' => new UploadedFile($path, $name, null, null, true)],
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_ROLES)) => P::roleFor(P::DEPLOY),
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_USER)) => TestHeaderAuthenticator::DEFAULT_USER,
            ],
        );

        return $this->client->getResponse();
    }
}
