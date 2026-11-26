<?php
// file generated with AI assistance: Claude Code - 2026-11-26 12:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Client;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Client\FlowableClientInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * The id rule of {@see FlowableClient}: every id that becomes an engine path
 * segment is checked before a request is sent, the deployment resource name
 * only for dot segments, and the multipart file name is escaped.
 */
final class FlowableClientIdRuleTest extends TestCase
{
    private const string BASE_URL = 'http://engine.invalid/flowable-rest';

    /** Interface methods whose id parameter goes into the query, not the path */
    private const array QUERY_ID_METHODS = ['getTaskFormData', 'getStartFormData'];

    /** @var list<array{method: string, url: string, body: string}> */
    private array $requests = [];

    /**
     * Every client call that puts an id into an engine path.
     *
     * @return array<string, \Closure(FlowableClient, string): mixed>
     */
    private static function idCalls(): array
    {
        return [
            'findDeployment' => static fn (FlowableClient $c, string $id) => $c->findDeployment($id),
            'deleteDeployment' => static fn (FlowableClient $c, string $id) => $c->deleteDeployment($id),
            'findProcessDefinition' => static fn (FlowableClient $c, string $id) => $c->findProcessDefinition($id),
            'findProcessInstance' => static fn (FlowableClient $c, string $id) => $c->findProcessInstance($id),
            'deleteProcessInstance' => static fn (FlowableClient $c, string $id) => $c->deleteProcessInstance($id),
            'findTask' => static fn (FlowableClient $c, string $id) => $c->findTask($id),
            'completeTask' => static fn (FlowableClient $c, string $id) => $c->completeTask($id, []),
            'findExecution' => static fn (FlowableClient $c, string $id) => $c->findExecution($id),
            'triggerExecution' => static fn (FlowableClient $c, string $id) => $c->triggerExecution($id, []),
            'getTaskVariables' => static fn (FlowableClient $c, string $id) => $c->getTaskVariables($id),
            'listDeploymentResources' => static fn (FlowableClient $c, string $id) => $c->listDeploymentResources($id),
            'getDeploymentResource' => static fn (FlowableClient $c, string $id) => $c->getDeploymentResource($id, 'a.bpmn'),
            'findHistoricProcessInstance' => static fn (FlowableClient $c, string $id) => $c->findHistoricProcessInstance($id),
            'deleteHistoricProcessInstance' => static fn (FlowableClient $c, string $id) => $c->deleteHistoricProcessInstance($id),
            'findHistoricTask' => static fn (FlowableClient $c, string $id) => $c->findHistoricTask($id),
            'findDmnDeployment' => static fn (FlowableClient $c, string $id) => $c->findDmnDeployment($id),
            'deleteDmnDeployment' => static fn (FlowableClient $c, string $id) => $c->deleteDmnDeployment($id),
            'findDecision' => static fn (FlowableClient $c, string $id) => $c->findDecision($id),
            'findHistoricDecisionExecution' => static fn (FlowableClient $c, string $id) => $c->findHistoricDecisionExecution($id),
            'findEventDeployment' => static fn (FlowableClient $c, string $id) => $c->findEventDeployment($id),
            'deleteEventDeployment' => static fn (FlowableClient $c, string $id) => $c->deleteEventDeployment($id),
            'findEventDefinition' => static fn (FlowableClient $c, string $id) => $c->findEventDefinition($id),
            'findChannelDefinition' => static fn (FlowableClient $c, string $id) => $c->findChannelDefinition($id),
            'completeExternalWorkerJob' => static fn (FlowableClient $c, string $id) => $c->completeExternalWorkerJob($id, []),
            'failExternalWorkerJob' => static fn (FlowableClient $c, string $id) => $c->failExternalWorkerJob($id, []),
            'bpmnErrorExternalWorkerJob' => static fn (FlowableClient $c, string $id) => $c->bpmnErrorExternalWorkerJob($id, []),
            'unacquireExternalWorkerJob' => static fn (FlowableClient $c, string $id) => $c->unacquireExternalWorkerJob($id, []),
            'findExternalWorkerJob' => static fn (FlowableClient $c, string $id) => $c->findExternalWorkerJob($id),
            'findJob' => static fn (FlowableClient $c, string $id) => $c->findJob($id),
            'getJobExceptionStacktrace' => static fn (FlowableClient $c, string $id) => $c->getJobExceptionStacktrace($id),
            'executeTimerJob' => static fn (FlowableClient $c, string $id) => $c->executeTimerJob($id),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function invalidIds(): array
    {
        return [
            'dot-dot' => '..',
            'dot' => '.',
            'slash' => 'a/b',
            'encoded dot-dot' => '%2e%2e',
            'empty' => '',
            'unicode' => 'täsk',
            'trailing newline' => "abc\n",
            'leading dot' => '.abc',
            'space' => 'a b',
            'backslash' => 'a\\b',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function validIds(): array
    {
        return [
            'numeric' => '12345',
            'uuid' => '8f14e45f-ceea-467f-a0e6-1e1a5f5f3c2b',
            'process definition id' => 'proc:1:42',
            'dotted key' => 'my.process_key-2:3:7',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(FlowableClient, string): mixed, string}>
     */
    public static function invalidIdCalls(): iterable
    {
        foreach (self::idCalls() as $method => $call) {
            foreach (self::invalidIds() as $label => $id) {
                yield $method . ' / ' . $label => [$call, $id];
            }
        }
    }

    /**
     * @return iterable<string, array{\Closure(FlowableClient, string): mixed, string}>
     */
    public static function validIdCalls(): iterable
    {
        foreach (self::idCalls() as $method => $call) {
            foreach (self::validIds() as $label => $id) {
                yield $method . ' / ' . $label => [$call, $id];
            }
        }
    }

    #[DataProvider('invalidIdCalls')]
    public function testInvalidIdIsRejectedBeforeAnyEngineRequest(\Closure $call, string $id): void
    {
        try {
            $call($this->client(), $id);
            self::fail('The invalid id was not rejected.');
        } catch (BadRequestHttpException $e) {
            self::assertSame(400, $e->getStatusCode());
            self::assertStringStartsWith('Invalid Flowable id', $e->getMessage());
        }
        self::assertSame([], $this->requests, 'a rejected id reached the engine');
    }

    #[DataProvider('validIdCalls')]
    public function testValidIdReachesTheEngineEncoded(\Closure $call, string $id): void
    {
        $call($this->client(), $id);

        self::assertCount(1, $this->requests);
        self::assertStringContainsString('/' . rawurlencode($id), $this->requests[0]['url']);
    }

    public function testNoSignatureAcceptsANonStringId(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore argument.type
        $this->client()->findTask(42);
    }

    /**
     * Guards the list above: a new interface method with an id parameter
     * must be added to {@see self::idCalls()}.
     */
    public function testEveryIdMethodOfTheInterfaceIsCovered(): void
    {
        $methods = [];
        foreach ((new \ReflectionClass(FlowableClientInterface::class))->getMethods() as $method) {
            foreach ($method->getParameters() as $parameter) {
                if (preg_match('/^(id|[a-z]+Id)$/', $parameter->getName()) === 1
                    && $parameter->getName() !== 'resourceId'
                    && !\in_array($method->getName(), self::QUERY_ID_METHODS, true)) {
                    $methods[] = $method->getName();
                    break;
                }
            }
        }
        sort($methods);
        $covered = array_keys(self::idCalls());
        sort($covered);

        self::assertSame($methods, $covered);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validResourceNames(): iterable
    {
        yield 'plain' => ['approve.schema.json'];
        yield 'with slash' => ['forms/approve.schema.json'];
        yield 'with space' => ['my form.schema.json'];
        yield 'dots inside a segment' => ['forms/..approve.schema.json'];
    }

    #[DataProvider('validResourceNames')]
    public function testResourceNameFromEngineDataPasses(string $resourceId): void
    {
        $this->client()->getDeploymentResource('d1', $resourceId);

        self::assertCount(1, $this->requests);
        self::assertSame(
            self::BASE_URL . '/service/repository/deployments/d1/resourcedata/' . rawurlencode($resourceId),
            $this->requests[0]['url'],
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dotSegmentResourceNames(): iterable
    {
        yield 'dot-dot' => ['..'];
        yield 'dot' => ['.'];
        yield 'leading dot-dot segment' => ['../a.schema.json'];
        yield 'inner dot-dot segment' => ['forms/../a.schema.json'];
        yield 'inner dot segment' => ['forms/./a.schema.json'];
        yield 'trailing dot-dot segment' => ['forms/..'];
    }

    #[DataProvider('dotSegmentResourceNames')]
    public function testResourceNameWithDotSegmentIsRejected(string $resourceId): void
    {
        try {
            $this->client()->getDeploymentResource('d1', $resourceId);
            self::fail('The dot segment was not rejected.');
        } catch (BadRequestHttpException $e) {
            self::assertStringStartsWith('Invalid Flowable resource name', $e->getMessage());
        }
        self::assertSame([], $this->requests);
    }

    /**
     * @return iterable<string, array{\Closure(FlowableClient, string): mixed}>
     */
    public static function uploads(): iterable
    {
        yield 'createDeployment' => [static fn (FlowableClient $c, string $name) => $c->createDeployment($name, '<x/>', ['deploymentName' => 'n'])];
        yield 'createDmnDeployment' => [static fn (FlowableClient $c, string $name) => $c->createDmnDeployment($name, '<x/>')];
        yield 'createEventDeployment' => [static fn (FlowableClient $c, string $name) => $c->createEventDeployment($name, '{}')];
    }

    #[DataProvider('uploads')]
    public function testValidFileNameIsSentUnchanged(\Closure $upload): void
    {
        $upload($this->client(), 'process-1.bpmn20.xml');

        self::assertStringContainsString(
            "\r\nContent-Disposition: form-data; name=\"file\"; filename=\"process-1.bpmn20.xml\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n",
            $this->requests[0]['body'],
        );
    }

    #[DataProvider('uploads')]
    public function testQuotesAndLineBreaksInTheFileNameCannotLeaveTheHeader(\Closure $upload): void
    {
        $upload($this->client(), "a\"; name=\"x\r\nX-Injected: 1\n.bpmn");

        $body = $this->requests[0]['body'];
        self::assertStringContainsString(
            'Content-Disposition: form-data; name="file"; filename="a%22; name=%22x%0D%0AX-Injected: 1%0A.bpmn"' . "\r\n",
            $body,
        );
        self::assertStringNotContainsString("\nX-Injected", $body);
        self::assertSame(1, substr_count($body, 'name="file"'));
    }

    private function client(): FlowableClient
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = $options['body'] ?? '';
            if (\is_callable($body)) {
                $chunks = '';
                while ('' !== $chunk = (string) $body(16384)) {
                    $chunks .= $chunk;
                }
                $body = $chunks;
            }
            $this->requests[] = ['method' => $method, 'url' => $url, 'body' => (string) $body];

            return new MockResponse('{"id":"stub"}', ['response_headers' => ['Content-Type: application/json']]);
        });

        return new FlowableClient($http, self::BASE_URL, 'basic', 'user', 'secret', null);
    }
}
