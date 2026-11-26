<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:31:37 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Dmstr\Flowable\Tests\Http\ApiTestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Proves the test infrastructure itself: the failure mode of
 * {@see TestFlowableEngine}, kernel variants of {@see TestKernel} and the
 * {@see RecordingLogger} on the `flowable` channel.
 *
 * Error responses are asserted by status class only, not by message: the
 * messages are the bundle's business and may change.
 */
final class TestInfrastructureTest extends ApiTestCase
{
    private const string DEFINITIONS = self::FLOWABLE . '/process_definitions';

    private const string ENGINE_DEFINITIONS = '/flowable-rest/service/repository/process-definitions';

    private const string ADMIN = 'ROLE_FLOWABLE_ADMIN';

    public function testEngineAnswersAsBeforeWhenNoFailureIsConfigured(): void
    {
        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testEngineHttpErrorSurfacesAsServerError(): void
    {
        $this->engine()->failWith(
            500,
            TestFlowableEngine::errorBody(
                'Internal server error',
                "Could not reach http://flowable-db.internal:5432/flowable for {url}\n"
                    . "\tat org.flowable.engine.impl.Foo.bar(Foo.java:42)\n"
                    . "\tat org.flowable.engine.impl.Baz.qux(Baz.java:7)",
            ),
            pathContains: '/process-definitions',
        );

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertGreaterThanOrEqual(500, $response->getStatusCode(), (string) $response->getContent());
        self::assertContains(['method' => 'GET', 'path' => self::ENGINE_DEFINITIONS], $this->engine()->requests);
    }

    public function testEngineHttpErrorAppliesToMatchingRequestsOnlyAndCountsDown(): void
    {
        $this->engine()->failWith(
            400,
            TestFlowableEngine::errorBody(str_repeat("Bad request: line\n", 200)),
            pathContains: '/process-definitions',
            method: 'GET',
            times: 1,
        );

        $failed = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);
        $again = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertGreaterThanOrEqual(400, $failed->getStatusCode(), (string) $failed->getContent());
        self::assertSame(200, $again->getStatusCode(), (string) $again->getContent());
    }

    public function testEngineNotFoundSurfacesAsClientError(): void
    {
        $this->engine()->failWith(404, TestFlowableEngine::errorBody('Not found', "Could not find a process definition with id 'def-1'."));

        $response = $this->requestAs('GET', self::DEFINITIONS . '/def-1', ['ROLE_USER']);

        self::assertGreaterThanOrEqual(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertLessThan(500, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testResetRestoresTheDefaultAnswer(): void
    {
        $this->engine()->failWith(500, 'boom');
        $this->engine()->reset();

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testTransportErrorIsThrownWhenTheStatusIsRead(): void
    {
        /** @var HttpClientInterface $http */
        $http = static::getContainer()->get('http_client');
        $url = TestFlowableEngine::BASE_URL . '/service/repository/process-definitions';

        foreach (['failWithTimeout' => TestFlowableEngine::TIMEOUT_MESSAGE, 'failWithConnectionRefused' => TestFlowableEngine::CONNECTION_REFUSED_MESSAGE] as $fail => $message) {
            $this->engine()->reset();
            $this->engine()->{$fail}();
            try {
                $http->request('GET', $url)->getStatusCode();
                self::fail('No transport exception for ' . $fail);
            } catch (TransportExceptionInterface $e) {
                self::assertSame(str_replace('{url}', $url, $message), $e->getMessage());
            }
        }
    }

    public function testTransportErrorSurfacesAsServerError(): void
    {
        foreach (['timeout' => 'failWithTimeout', 'connection refused' => 'failWithConnectionRefused'] as $case => $fail) {
            $this->engine()->reset();
            $this->engine()->{$fail}();

            $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

            self::assertGreaterThanOrEqual(500, $response->getStatusCode(), $case . ': ' . $response->getContent());
        }
    }

    public function testKernelOptionsGiveOneEnvironmentPerConfiguration(): void
    {
        $readOff = ['mcp' => ['read' => false, 'write' => true, 'deploy' => true]];

        $plain = new TestKernel(TestKernel::ENV_WITH_VOTER, false);
        $variant = new TestKernel(TestKernel::ENV_WITH_VOTER, false, $readOff);
        $noVoterVariant = new TestKernel(TestKernel::ENV_NO_VOTER, false, $readOff);

        self::assertSame(TestKernel::ENV_WITH_VOTER, $plain->getEnvironment());
        self::assertStringStartsWith(TestKernel::ENV_WITH_VOTER . '_', $variant->getEnvironment());
        self::assertSame($variant->getEnvironment(), (new TestKernel(TestKernel::ENV_WITH_VOTER, false, $readOff))->getEnvironment());
        self::assertNotSame(
            $variant->getEnvironment(),
            (new TestKernel(TestKernel::ENV_WITH_VOTER, false, ['use_symfony_listeners' => true]))->getEnvironment(),
        );
        self::assertTrue($variant->hasPermissionVoter());
        self::assertFalse($noVoterVariant->hasPermissionVoter());

        $this->expectException(\InvalidArgumentException::class);
        new TestKernel(TestKernel::ENV_WITH_VOTER, false, ['unknown' => true]);
    }

    public function testReadSwitchOffRemovesTheReadTools(): void
    {
        self::assertContains('flowable_list_process_definitions', $this->mcpToolNames([self::ADMIN]));

        $this->useKernelVariant(['mcp' => ['read' => false, 'write' => true, 'deploy' => true]]);
        $tools = $this->mcpToolNames([self::ADMIN]);

        self::assertNotContains('flowable_list_process_definitions', $tools);
        self::assertContains('flowable_start_process', $tools);
    }

    public function testKernelWithSymfonyListenersServesARequest(): void
    {
        $this->useKernelVariant(['use_symfony_listeners' => true]);

        self::assertTrue(static::getContainer()->getParameter('api_platform.use_symfony_listeners'));
        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, self::json($response)['totalItems'] ?? null);
    }

    public function testRecordingLoggerReceivesTheAuditEntryOfAWrite(): void
    {
        $this->flowableLog()->clear();

        $response = $this->requestAs('POST', self::DEFINITIONS . '/def-1/start', [self::ADMIN], [], user: 'alice');

        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
        $records = $this->flowableLog()->recordsWithMessage('flowable.process_definition.start');
        self::assertCount(1, $records, implode(', ', $this->flowableLog()->messages()));
        self::assertSame('info', $records[0]['level']);
        self::assertSame('alice', $records[0]['context']['actor'] ?? null);
        self::assertSame('success', $records[0]['context']['state'] ?? null);
        self::assertSame('http', $records[0]['context']['via'] ?? null);
        self::assertCount(1, $this->flowableLog()->recordsWithMessage('flowable.process_definition.start.attempt'));
    }

    public function testRecordsDoNotLeakIntoTheNextTest(): void
    {
        // Runs after the write above: a new kernel has a new logger
        self::assertSame([], $this->flowableLog()->recordsWithMessage('flowable.process_definition.start'));
        self::assertSame([], $this->engine()->requests);
    }
}
// - revised 2026-11-26 (audit entry with state and origin, attempt entry)
