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

    // --- helpers ---------------------------------------------------------------

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
