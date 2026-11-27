<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:28:46 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * The input rules of the event registry, over HTTP and the CLI:
 *
 *  - the event/channel upload (POST /event_deployments/upload and
 *    flowable:events:deployments:upload) accepts ".event" and ".channel" in
 *    any case and refuses everything else with the same message, the
 *    archives (".bar", ".zip"), process and decision resources (".bpmn",
 *    ".bpmn20.xml", ".dmn", ".dmn.xml", ".cmmn.xml") and a name without
 *    extension among them, in upper and lower case;
 *  - an event instance (POST /event_instances and
 *    flowable:events:instances:create, both validated against
 *    FlowEventInstance/create.input.json) needs an event definition
 *    reference and a channel definition reference (key or id each, both may
 *    be given), takes `eventPayload` only as a JSON object and no unknown
 *    property; the HTTP operation and the command reject the same input
 *    with the same message, and the same accepted input reaches the engine
 *    with the same body.
 *
 * Nothing rejected reaches the engine. UploadSuffixRuleTest and
 * DmnAndEventUploadTest cover the other upload cases.
 */
final class EventRegistryInputTest extends ApiTestCase
{
    private const string USER = 'test-user';

    private const string EVENT_HINT = 'Allowed: event, channel (the event registry accepts no .bar/.zip bundles).';

    private const string VALIDATION_PREFIX = 'Input validation failed: ';

    private const string REFERENCES = '"eventDefinitionKey": "orderCreated", "channelDefinitionKey": "inbound"';

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
            @rmdir(\dirname($file));
        }
        parent::tearDown();
    }

    // --- event/channel upload --------------------------------------------------------

    /**
     * @return iterable<string, array{string, string}> file name, the suffix the error names
     */
    public static function rejectedUploadNames(): iterable
    {
        yield 'a.bar' => ['a.bar', '.bar'];
        yield 'A.BAR' => ['A.BAR', '.bar'];
        yield 'a.zip' => ['a.zip', '.zip'];
        yield 'A.Zip' => ['A.Zip', '.zip'];
        yield 'a.bpmn20.xml' => ['a.bpmn20.xml', '.xml'];
        yield 'A.BPMN20.XML' => ['A.BPMN20.XML', '.xml'];
        yield 'a.bpmn' => ['a.bpmn', '.bpmn'];
        yield 'a.dmn' => ['a.dmn', '.dmn'];
        yield 'A.DMN' => ['A.DMN', '.dmn'];
        yield 'a.dmn.xml' => ['a.dmn.xml', '.dmn.xml'];
        yield 'A.Dmn.Xml' => ['A.Dmn.Xml', '.dmn.xml'];
        yield 'a.cmmn.xml' => ['a.cmmn.xml', '.cmmn.xml'];
        yield 'a.event.json' => ['a.event.json', '.json'];
        yield 'no extension' => ['events', '.'];
    }

    #[DataProvider('rejectedUploadNames')]
    public function testHttpEventUploadRejectsTheSuffix(string $name, string $suffix): void
    {
        $response = $this->upload($name);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(
            'Unsupported resource extension "' . $suffix . '". ' . self::EVENT_HINT,
            self::json($response)['detail'] ?? null,
        );
        self::assertSame([], $this->engine()->requests);
    }

    #[DataProvider('rejectedUploadNames')]
    public function testCliEventUploadRejectsTheSuffixWithTheSameMessage(string $name, string $suffix): void
    {
        $tester = $this->runCommand('flowable:events:deployments:upload', ['--file' => $this->tempFile($name, '<event/>')]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(
            self::withoutWhitespace('Unsupported resource extension "' . $suffix . '". ' . self::EVENT_HINT),
            self::withoutWhitespace($tester->getDisplay()),
        );
        self::assertSame([], $this->engine()->requests);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedUploadNames(): iterable
    {
        foreach (['order.EVENT', 'Order.Event', 'inbound.CHANNEL', 'Inbound.Channel'] as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('acceptedUploadNames')]
    public function testHttpEventUploadTakesEventAndChannelInAnyCase(string $name): void
    {
        $response = $this->upload($name);

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, $this->engine()->requests);
        self::assertStringEndsWith('/event-registry-repository/deployments', $this->engine()->requests[0]['path']);
    }

    // --- event instances ---------------------------------------------------------------

    /**
     * Bodies the input schema refuses, as raw JSON.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedEventBodies(): iterable
    {
        yield 'payload as list' => ['{' . self::REFERENCES . ', "eventPayload": [1, 2]}'];
        yield 'payload as empty list' => ['{' . self::REFERENCES . ', "eventPayload": []}'];
        yield 'payload as list of objects' => ['{' . self::REFERENCES . ', "eventPayload": [{"amount": 1}]}'];
        yield 'payload as string' => ['{' . self::REFERENCES . ', "eventPayload": "amount=1"}'];
        yield 'payload as number' => ['{' . self::REFERENCES . ', "eventPayload": 42}'];
        yield 'payload as boolean' => ['{' . self::REFERENCES . ', "eventPayload": true}'];
        yield 'payload null' => ['{' . self::REFERENCES . ', "eventPayload": null}'];
        yield 'no channel reference' => ['{"eventDefinitionKey": "orderCreated", "eventPayload": {}}'];
        yield 'no event reference' => ['{"channelDefinitionId": "channel-1", "eventPayload": {}}'];
        yield 'no reference at all' => ['{"eventPayload": {"amount": 1}}'];
        yield 'empty body' => ['{}'];
        yield 'reference not a string' => ['{"eventDefinitionKey": 7, "channelDefinitionKey": "inbound"}'];
        yield 'unknown property' => ['{' . self::REFERENCES . ', "payload": {"amount": 1}}'];
    }

    #[DataProvider('rejectedEventBodies')]
    public function testHttpAndCliRejectTheSameEventInstanceWithTheSameMessage(string $body): void
    {
        $response = $this->sendEvent($body);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $detail = (string) (self::json($response)['detail'] ?? '');
        self::assertStringStartsWith(self::VALIDATION_PREFIX, $detail);

        $tester = $this->runCommand('flowable:events:instances:create', ['--input' => $body]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString(self::withoutWhitespace($detail), self::withoutWhitespace($tester->getDisplay()));
        self::assertSame([], $this->engine()->requests, 'a rejected event reached the engine');
    }

    public function testPayloadErrorNamesTheTypeObject(): void
    {
        $response = $this->sendEvent('{' . self::REFERENCES . ', "eventPayload": [1, 2]}');

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        $detail = (string) (self::json($response)['detail'] ?? '');
        self::assertStringContainsString('/eventPayload', $detail);
        self::assertStringContainsString('object', $detail);
    }

    /**
     * Bodies the input schema accepts, as raw JSON, and the body the engine
     * receives.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function acceptedEventBodies(): iterable
    {
        yield 'keys and object payload' => [
            '{' . self::REFERENCES . ', "eventPayload": {"amount": 1, "items": [1, 2]}}',
            '{"eventDefinitionKey":"orderCreated","channelDefinitionKey":"inbound","eventPayload":{"amount":1,"items":[1,2]}}',
        ];
        yield 'empty object payload stays an object' => [
            '{' . self::REFERENCES . ', "eventPayload": {}}',
            '{"eventDefinitionKey":"orderCreated","channelDefinitionKey":"inbound","eventPayload":{}}',
        ];
        yield 'ids, no payload' => [
            '{"eventDefinitionId": "event-1", "channelDefinitionId": "channel-1"}',
            '{"eventDefinitionId":"event-1","channelDefinitionId":"channel-1"}',
        ];
        yield 'key and id of both, tenant' => [
            '{"eventDefinitionKey": "orderCreated", "eventDefinitionId": "event-1", "channelDefinitionKey": "inbound", "channelDefinitionId": "channel-1", "tenantId": "tenant-1"}',
            '{"eventDefinitionId":"event-1","eventDefinitionKey":"orderCreated","channelDefinitionId":"channel-1","channelDefinitionKey":"inbound","tenantId":"tenant-1"}',
        ];
    }

    #[DataProvider('acceptedEventBodies')]
    public function testHttpAndCliSendTheSameAcceptedEventInstance(string $body, string $engineBody): void
    {
        $response = $this->sendEvent($body);
        self::assertSame(204, $response->getStatusCode(), (string) $response->getContent());

        $tester = $this->runCommand('flowable:events:instances:create', ['--input' => $body]);
        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        self::assertCount(2, $this->engine()->requests);
        foreach ($this->engine()->requests as $i => $request) {
            self::assertSame('POST', $request['method']);
            self::assertStringEndsWith('/event-registry-api/event-registry-runtime/event-instances', $request['path']);
            self::assertJsonStringEqualsJsonString($engineBody, (string) $this->engine()->bodies[$i]);
            self::assertSame($engineBody, (string) $this->engine()->bodies[$i], 'the {} / [] distinction and the key order are kept');
        }
    }

    // --- helpers -------------------------------------------------------------------------

    private function sendEvent(string $rawBody): Response
    {
        $this->client->request(
            'POST',
            self::FLOWABLE . '/event_instances',
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'CONTENT_TYPE' => 'application/ld+json',
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_ROLES)) => P::roleFor(P::EVENT_SEND),
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_USER)) => self::USER,
            ],
            content: $rawBody,
        );

        return $this->client->getResponse();
    }

    private function upload(string $name): Response
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        $this->tempFiles[] = $path;
        file_put_contents($path, '<event/>');

        $this->client->request(
            'POST',
            self::FLOWABLE . '/event_deployments/upload',
            files: ['file' => new UploadedFile($path, $name, null, null, true)],
            server: [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_ROLES)) => P::roleFor(P::DEPLOY),
                'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_USER)) => self::USER,
            ],
        );

        return $this->client->getResponse();
    }

    /** A file named $name, alone in a new directory. */
    private function tempFile(string $name, string $content): string
    {
        $dir = sys_get_temp_dir() . '/event-input-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $path = $dir . '/' . $name;
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * @param array<string, string> $options
     */
    private function runCommand(string $name, array $options): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute($options + ['--acting-user' => self::USER]);

        return $tester;
    }

    /** SymfonyStyle wraps and indents long messages, also inside a word. */
    private static function withoutWhitespace(string $text): string
    {
        return (string) preg_replace('/\s+/', '', $text);
    }
}
