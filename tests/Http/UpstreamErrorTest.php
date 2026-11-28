<?php
// file generated with AI assistance: Claude Code - 2026-11-26 14:38:26 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Client\FlowableClient;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Tests\Kernel\TestFlowableEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;

/**
 * Engine failures over the real transports (HTTP, MCP, CLI, health): a 502
 * or 504 carries a generic message with a correlation id and nothing the
 * engine or the transport said; the details are in one error entry of the
 * `flowable` log with the same id. A rejected request (422) passes on the
 * first line of the engine message, shortened; a 404 is unchanged.
 */
final class UpstreamErrorTest extends ApiTestCase
{
    private const string DEFINITIONS = self::FLOWABLE . '/process_definitions';

    private const string ADMIN = 'ROLE_FLOWABLE_ADMIN';

    private const string INTERNAL_HOST = 'flowable-db.internal';

    private const string ENGINE_TEXT = 'Internal server error';

    private const string STACK_FRAME = 'org.flowable.engine.impl.Foo.bar';

    /** Must not appear in anything a caller gets for a 502/504 */
    private const array LEAKS = [
        'flowable.invalid',
        'flowable-rest',
        self::INTERNAL_HOST,
        self::ENGINE_TEXT,
        self::STACK_FRAME,
        'Idle timeout',
        'Could not connect',
        'Failed to connect',
        'TransportException',
    ];

    private const string ID_PATTERN = '/correlation id: ([0-9a-f]{16})\)/';

    /**
     * @return iterable<string, array{string, int, string}> failure, status, text the log must carry
     */
    public static function failures(): iterable
    {
        yield 'engine 500' => ['500', 502, self::INTERNAL_HOST];
        yield 'timeout' => ['timeout', 504, 'Idle timeout'];
        yield 'connection refused' => ['refused', 504, 'Could not connect'];
    }

    #[DataProvider('failures')]
    public function testHttpInDebugModeAnswersGenericallyWithTheCorrelationIdHeader(string $failure, int $status, string $logged): void
    {
        $this->makeEngineFail($failure);

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame($status, $response->getStatusCode(), (string) $response->getContent());
        $body = (string) $response->getContent();
        self::assertNoLeak($body);
        $id = $this->assertLogged($body, $status, $logged);
        self::assertSame($id, $response->headers->get(FlowableApiException::CORRELATION_ID_HEADER));
        $expected = sprintf($status === 504 ? FlowableApiException::UNREACHABLE : FlowableApiException::BACKEND_ERROR, $id);
        self::assertSame($expected, self::json($response)['detail'] ?? null);
    }

    public function testHttpWithoutDebugSendsTheCorrelationIdHeader(): void
    {
        $this->useKernelVariant([], debug: false);

        foreach (self::failures() as $case => [$failure, $status, $logged]) {
            $this->engine()->reset();
            $this->flowableLog()->clear();
            $this->makeEngineFail($failure);

            $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

            self::assertSame($status, $response->getStatusCode(), $case . ': ' . $response->getContent());
            self::assertNoLeak((string) $response->getContent());
            $id = (string) $response->headers->get(FlowableApiException::CORRELATION_ID_HEADER);
            self::assertMatchesRegularExpression('/\A[0-9a-f]{16}\z/', $id, $case);
            $this->assertLogged(sprintf('(correlation id: %s)', $id), $status, $logged);
        }
    }

    #[DataProvider('failures')]
    public function testMcpToolCallAnswersGenerically(string $failure, int $status, string $logged): void
    {
        $this->makeEngineFail($failure);

        $result = $this->mcpCallTool('flowable_list_process_definitions', [], [self::ADMIN]);

        $text = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $text);
        self::assertNoLeak($text);
        $this->assertLogged($text, $status, $logged);
    }

    #[DataProvider('failures')]
    public function testCommandPrintsTheGenericMessage(string $failure, int $status, string $logged): void
    {
        $this->makeEngineFail($failure);

        $tester = $this->runCommand('flowable:process-definitions', []);

        self::assertSame(1, $tester->getStatusCode());
        $display = self::normalized($tester->getDisplay());
        self::assertNoLeak($display);
        $id = $this->assertLogged($display, $status, $logged);
        $expected = sprintf($status === 504 ? FlowableApiException::UNREACHABLE : FlowableApiException::BACKEND_ERROR, $id);
        self::assertStringContainsString($expected, $display);
    }

    #[DataProvider('failures')]
    public function testHealthCommandKeepsTheEndpointLineAndHidesTheCause(string $failure, int $status, string $logged): void
    {
        $this->makeEngineFail($failure);

        $tester = $this->runCommand('flowable:health', []);

        $lines = explode("\n", trim($tester->getDisplay()), 2);
        self::assertSame('endpoint: ' . TestFlowableEngine::BASE_URL, trim($lines[0]));
        $health = json_decode($lines[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('DOWN', $health['status']);
        self::assertSame($status !== 504, $health['reachable']);
        self::assertSame($status === 504 ? 1 : 0, $tester->getStatusCode());
        self::assertNoLeak($lines[1]);
        $id = $this->assertLogged($health['detail'], $status, $logged);
        $expected = sprintf($status === 504 ? FlowableApiException::UNREACHABLE : FlowableApiException::BACKEND_ERROR, $id);
        self::assertSame($expected, $health['detail']);
    }

    #[DataProvider('failures')]
    public function testHealthInfoHasOnlyAGenericDetail(string $failure, int $status, string $logged): void
    {
        $this->makeEngineFail($failure);

        $health = $this->locator()->resolve()->getHealthInfo();

        self::assertSame(['status', 'reachable', 'detail'], array_keys($health));
        self::assertNoLeak($health['detail']);
        $this->assertLogged($health['detail'], $status, $logged);
    }

    public function testRejectedRequestPassesOnTheFirstLineOfTheEngineMessage(): void
    {
        $this->engine()->failWith(400, TestFlowableEngine::errorBody("Variable 'amount' is not a number.\nat org.flowable.Foo({url})"));

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame("Flowable rejected the request: Variable 'amount' is not a number.", self::json($response)['detail'] ?? null);
        self::assertNull($response->headers->get(FlowableApiException::CORRELATION_ID_HEADER));
    }

    public function testRejectedRequestShortensAVeryLongEngineMessage(): void
    {
        $this->engine()->failWith(400, TestFlowableEngine::errorBody(str_repeat('x', 5000)));

        $result = $this->mcpCallTool('flowable_list_process_definitions', [], [self::ADMIN]);

        $text = json_encode($result, JSON_THROW_ON_ERROR);
        $expected = 'Flowable rejected the request: '
            . str_repeat('x', FlowableApiException::ENGINE_MESSAGE_MAX_LENGTH) . '...';
        self::assertStringContainsString($expected, $text);
        self::assertStringNotContainsString(str_repeat('x', FlowableApiException::ENGINE_MESSAGE_MAX_LENGTH + 1), $text);
    }

    public function testRejectedRequestOverTheCommandLine(): void
    {
        $this->engine()->failWith(400, TestFlowableEngine::errorBody("Bad request: first line\nsecond line from {url}"));

        $display = self::normalized($this->runCommand('flowable:process-definitions', [])->getDisplay());

        self::assertStringContainsString('Flowable rejected the request: Bad request: first line', $display);
        self::assertStringNotContainsString('second line', $display);
        self::assertNoLeak($display);
    }

    public function testNotFoundIsUnchanged(): void
    {
        $this->engine()->failWith(404, TestFlowableEngine::errorBody('Not found', "Could not find {url}"));

        $response = $this->requestAs('GET', self::DEFINITIONS . '/def-1', ['ROLE_USER']);

        self::assertSame(404, $response->getStatusCode(), (string) $response->getContent());
        self::assertNull($response->headers->get(FlowableApiException::CORRELATION_ID_HEADER));
        self::assertSame([], $this->flowableLog()->recordsWithMessage(FlowableClient::LOG_UPSTREAM_ERROR));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function errorsWithoutAMessage(): iterable
    {
        foreach ([400 => 'rejected', 500 => 'backend'] as $status => $kind) {
            yield $kind . ', empty body' => [$status, ''];
            yield $kind . ', no JSON' => [$status, 'Proxy error at http://flowable-db.internal/x'];
            yield $kind . ', JSON without message' => [$status, '{"error": "flowable-db.internal said no"}'];
            yield $kind . ', empty message' => [$status, '{"message": "  ", "exception": ""}'];
            yield $kind . ', message not a string' => [$status, '{"message": {"host": "flowable-db.internal"}}'];
        }
    }

    #[DataProvider('errorsWithoutAMessage')]
    public function testErrorWithoutAUsableMessageNeverPassesOnTheBody(int $status, string $body): void
    {
        $this->engine()->failWith($status, $body);

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);
        $detail = self::json($response)['detail'] ?? null;

        if ($status === 400) {
            self::assertSame(422, $response->getStatusCode());
            self::assertSame(FlowableApiException::REJECTED, $detail);
        } else {
            self::assertSame(502, $response->getStatusCode());
            self::assertMatchesRegularExpression('/\AFlowable backend error \(correlation id: [0-9a-f]{16}\)\.\z/', (string) $detail);
        }
        self::assertNoLeak((string) $response->getContent());
    }

    // --- the engine's status (503) reaches no caller ---------------------------
    //
    // The engine answers 503, a status that appears in no message template
    // (the bundle answers 502). Every output is compared exactly where its
    // shape allows; a substring check for "503" or "upstream" only runs after
    // the random correlation id (16 hex characters, may contain "503") has
    // been replaced by a placeholder, and never over a debug trace (file
    // names, line numbers and function names such as upstreamError()).

    private const int ENGINE_STATUS = 503;

    private const string ID_PLACEHOLDER = '<correlation-id>';

    /** Headers of a 502 problem response in 0.16.0, without debug, except date and correlation id */
    private const array PROBLEM_HEADERS = [
        'vary' => ['Accept'],
        'x-content-type-options' => ['nosniff'],
        'x-frame-options' => ['deny'],
        'content-type' => ['application/problem+json'],
        'cache-control' => ['no-cache, private'],
        'link' => ['<http://www.w3.org/ns/hydra/error>; rel="http://www.w3.org/ns/json-ld#error",'
            . '<http://localhost/api/docs.jsonld>; rel="http://www.w3.org/ns/hydra/core#apiDocumentation"'],
    ];

    public function testHttpWithoutDebugShowsNoUpstreamStatus(): void
    {
        $this->useKernelVariant([], debug: false);
        $this->makeEngineAnswer503();

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        $id = $this->assertLogged503();
        self::assertSame(502, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/502',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => 'Internal Server Error',
            'status' => 502,
            'type' => '/errors/502',
            'description' => 'Internal Server Error',
        ], self::json($response));
        $this->assertProblemHeaders($response, $id, self::PROBLEM_HEADERS);
        self::assertNoUpstreamStatus((string) $response->getContent(), $id);
        self::assertNoLeak((string) $response->getContent());
    }

    public function testHttpInDebugModeShowsNoUpstreamStatusOutsideTheTrace(): void
    {
        $this->makeEngineAnswer503();

        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        $id = $this->assertLogged503();
        self::assertSame(502, $response->getStatusCode(), (string) $response->getContent());
        $body = self::json($response);
        // The trace names files, lines and functions (upstreamError(), line
        // numbers such as 503) since before the upstream status existed; only
        // the structured fields are checked, exactly
        self::assertIsArray($body['trace'] ?? null);
        unset($body['trace']);
        $message = sprintf(FlowableApiException::BACKEND_ERROR, $id);
        self::assertSame([
            '@context' => '/api/contexts/Error',
            '@id' => '/api/errors/502',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $message,
            'status' => 502,
            'type' => '/errors/502',
            'description' => $message,
        ], $body);
        $this->assertProblemHeaders($response, $id, self::PROBLEM_HEADERS + ['x-robots-tag' => ['noindex']]);
    }

    public function testMcpToolErrorShowsNoUpstreamStatus(): void
    {
        $this->makeEngineAnswer503();

        $result = $this->mcpCallTool('flowable_list_process_definitions', [], [self::ADMIN]);

        $id = $this->assertLogged503();
        self::assertSame([
            'jsonrpc' => '2.0',
            'id' => $result['id'] ?? null,
            'error' => ['code' => -32603, 'message' => sprintf(FlowableApiException::BACKEND_ERROR, $id)],
        ], $result);
        $text = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertNoUpstreamStatus($text, $id);
        self::assertNoLeak($text);
    }

    public function testFailedWriteAuditEntryHasNoUpstreamStatus(): void
    {
        $this->makeEngineAnswer503();

        $result = $this->mcpCallTool('flowable_complete_task', ['id' => 't1'], [self::ADMIN]);

        $id = $this->assertLogged503();
        self::assertSame(
            ['code' => -32603, 'message' => sprintf(FlowableApiException::BACKEND_ERROR, $id)],
            $result['error'] ?? null,
        );
        $failures = $this->flowableLog()->recordsWithMessage('flowable.task.complete.failure');
        self::assertCount(1, $failures, implode(', ', $this->flowableLog()->messages()));
        self::assertSame([
            'state' => 'failure',
            'via' => 'mcp',
            'tool' => 'flowable_complete_task',
            'actor' => 'test-user',
            'task' => 't1',
            'status' => 502,
            'correlationId' => $id,
            'exception' => FlowableApiException::class,
        ], $failures[0]['context']);
    }

    public function testCommandOutputShowsNoUpstreamStatus(): void
    {
        $this->makeEngineAnswer503();

        $tester = $this->runCommand('flowable:process-definitions', []);

        $id = $this->assertLogged503();
        self::assertSame(1, $tester->getStatusCode());
        $display = trim(self::normalized($tester->getDisplay()));
        self::assertSame('[ERROR] ' . sprintf(FlowableApiException::BACKEND_ERROR, $id), $display);
        self::assertNoUpstreamStatus($tester->getDisplay(), $id);
    }

    public function testHealthCommandShowsNoUpstreamStatus(): void
    {
        $this->makeEngineAnswer503();

        $tester = $this->runCommand('flowable:health', []);

        $id = $this->assertLogged503();
        self::assertSame(0, $tester->getStatusCode());
        $lines = explode("\n", trim($tester->getDisplay()), 2);
        self::assertSame('endpoint: ' . TestFlowableEngine::BASE_URL, trim($lines[0]));
        self::assertSame(
            ['status' => 'DOWN', 'reachable' => true, 'detail' => sprintf(FlowableApiException::BACKEND_ERROR, $id)],
            json_decode($lines[1] ?? '', true, flags: JSON_THROW_ON_ERROR),
        );
        // The endpoint line is the test engine's URL, known not to contain it
        self::assertNoUpstreamStatus($tester->getDisplay(), $id);
    }

    public function testHealthInfoShowsNoUpstreamStatus(): void
    {
        $this->makeEngineAnswer503();

        $health = $this->locator()->resolve()->getHealthInfo();

        $id = $this->assertLogged503();
        self::assertSame(
            ['status' => 'DOWN', 'reachable' => true, 'detail' => sprintf(FlowableApiException::BACKEND_ERROR, $id)],
            $health,
        );
    }

    /**
     * Ties the tests above to the feature: the exception in play does carry
     * the engine's 503 (only through its getter), while its message and
     * headers stay the 0.16.0 ones. Needs FlowableApiException::getUpstreamStatus().
     */
    public function testTheExceptionCarriesTheUpstreamStatusOnlyThroughItsGetter(): void
    {
        $this->makeEngineAnswer503();

        try {
            $this->locator()->resolve()->listProcessDefinitions();
            self::fail('The engine call should have failed.');
        } catch (FlowableApiException $e) {
        }

        $id = $this->assertLogged503();
        self::assertSame($id, $e->getCorrelationId());
        self::assertSame(502, $e->getStatusCode());
        self::assertSame(sprintf(FlowableApiException::BACKEND_ERROR, $id), $e->getMessage());
        self::assertSame([FlowableApiException::CORRELATION_ID_HEADER => $id], $e->getHeaders());
        self::assertSame(self::ENGINE_STATUS, $e->getUpstreamStatus());
    }

    // --- helpers ---------------------------------------------------------------

    /** The engine message names the status too, so a passed-on message would show it as well. */
    private function makeEngineAnswer503(): void
    {
        $this->engine()->failWith(self::ENGINE_STATUS, TestFlowableEngine::errorBody(
            'HTTP ' . self::ENGINE_STATUS . ' Service Unavailable from ' . self::INTERNAL_HOST . ' at {url}',
            "Upstream status " . self::ENGINE_STATUS . "\n\tat " . self::STACK_FRAME . "(Foo.java:42)",
        ));
    }

    /**
     * Asserts the one flowable.upstream_error entry, which is the intended
     * place for the engine's status, and returns its correlation id.
     */
    private function assertLogged503(): string
    {
        $records = $this->flowableLog()->recordsWithMessage(FlowableClient::LOG_UPSTREAM_ERROR);
        self::assertCount(1, $records, implode(', ', $this->flowableLog()->messages()));
        $context = $records[0]['context'];
        self::assertSame('error', $records[0]['level']);
        self::assertSame(502, $context['status'] ?? null);
        self::assertSame(self::ENGINE_STATUS, $context['upstreamStatus'] ?? null);
        $id = $context['correlationId'] ?? null;
        self::assertIsString($id);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{16}\z/', $id);

        return $id;
    }

    /**
     * Exact header set and values of 0.16.0 (date only by format, the
     * correlation id by value), so neither a new header nor a changed value
     * can carry the status.
     *
     * @param array<string, list<string>> $expected
     */
    private function assertProblemHeaders(Response $response, string $id, array $expected): void
    {
        $headers = $response->headers->all();
        $names = array_keys($headers);
        sort($names);
        $expectedNames = [...array_keys($expected), 'date', 'x-correlation-id'];
        sort($expectedNames);
        self::assertSame($expectedNames, $names);
        foreach ($names as $name) {
            self::assertStringNotContainsStringIgnoringCase('upstream', $name);
        }

        self::assertSame([$id], $headers['x-correlation-id']);
        self::assertCount(1, $headers['date']);
        self::assertMatchesRegularExpression('/\A[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT\z/', $headers['date'][0]);
        unset($headers['date'], $headers['x-correlation-id']);
        ksort($headers);
        ksort($expected);
        self::assertSame($expected, $headers);
    }

    private static function assertNoUpstreamStatus(string $output, string $id): void
    {
        $scrubbed = str_replace($id, self::ID_PLACEHOLDER, $output);
        self::assertStringNotContainsString((string) self::ENGINE_STATUS, $scrubbed);
        self::assertStringNotContainsStringIgnoringCase('upstream', $scrubbed);
    }

    private function makeEngineFail(string $failure): void
    {
        match ($failure) {
            '500' => $this->engine()->failWith(500, TestFlowableEngine::errorBody(
                self::ENGINE_TEXT . ' at {url}',
                'Could not reach http://' . self::INTERNAL_HOST . ":5432/flowable for {url}\n"
                    . "\tat " . self::STACK_FRAME . "(Foo.java:42)",
            )),
            'timeout' => $this->engine()->failWithTimeout(),
            'refused' => $this->engine()->failWithConnectionRefused(),
        };
    }

    private static function assertNoLeak(string $output): void
    {
        foreach (self::LEAKS as $leak) {
            self::assertStringNotContainsString($leak, $output, 'leaked: ' . $leak);
        }
    }

    /**
     * Finds the correlation id in the output and asserts the one error entry
     * of the `flowable` log with that id and the details.
     *
     * @return string the correlation id
     */
    private function assertLogged(string $output, int $status, string $logged): string
    {
        self::assertMatchesRegularExpression(self::ID_PATTERN, $output);
        preg_match(self::ID_PATTERN, $output, $match);
        $id = $match[1];

        $records = $this->flowableLog()->recordsWithMessage(FlowableClient::LOG_UPSTREAM_ERROR);
        self::assertCount(1, $records, implode(', ', $this->flowableLog()->messages()));
        $record = $records[0];
        self::assertSame('error', $record['level']);
        self::assertSame($id, $record['context']['correlationId'] ?? null);
        self::assertSame($status, $record['context']['status'] ?? null);
        self::assertStringContainsString(TestFlowableEngine::BASE_URL, (string) ($record['context']['url'] ?? ''));
        self::assertStringContainsString($logged, json_encode($record['context'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        if ($status === 502) {
            self::assertSame(500, $record['context']['upstreamStatus'] ?? null);
        } else {
            self::assertNotEmpty($record['context']['exception'] ?? null);
        }

        return $id;
    }

    private function locator(): FlowableClientLocator
    {
        return static::getContainer()->get(FlowableClientLocator::class);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(string $name, array $input): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel))->find($name));
        $tester->execute($input);

        return $tester;
    }

    /** SymfonyStyle wraps and indents the error block. */
    private static function normalized(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }
}
// - revised 2026-11-28 (upstream status leak tests over HTTP, MCP and CLI)
