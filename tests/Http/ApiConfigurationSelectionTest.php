<?php
// file generated with AI assistance: Claude Code - 2026-11-26 12:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\ApiConfiguration\Entity\ApiConfiguration;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * An explicit apiConfiguration selector picks exactly one active `flowable`
 * configuration by its first 8 characters or its full lowercase UUID. Every
 * other selector ends in the same generic 400 over HTTP, MCP, the console
 * commands and the external worker, and reaches neither the engine nor
 * another configuration.
 *
 * Besides the default configuration of {@see ApiTestCase::setUp()} each test
 * stores a selectable configuration (requests to it land under
 * {@see self::SELECTED_PATH}), an inactive one, one of another type and two
 * sharing an 8-character prefix, all against the suite's SQLite database.
 */
final class ApiConfigurationSelectionTest extends ApiTestCase
{
    private const string SELECTED = '0a1b2c3d-1111-4111-8111-111111111111';
    private const string SELECTED_PATH = '/selected-rest/';
    private const string INACTIVE = '1a2b3c4d-2222-4222-8222-222222222222';
    private const string OTHER_TYPE = '2b3c4d5e-3333-4333-8333-333333333333';
    private const string OTHER_TYPE_NAME = 'othertype';
    private const string SHARED_PREFIX = '3c4d5e6f';
    private const string SHARED_A = self::SHARED_PREFIX . '-4444-4444-8444-444444444444';
    private const string SHARED_B = self::SHARED_PREFIX . '-5555-4555-8555-555555555555';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createApiConfiguration(self::SELECTED, baseUrl: 'http://flowable.invalid' . rtrim(self::SELECTED_PATH, '/'));
        $this->createApiConfiguration(self::INACTIVE, active: false);
        $this->createApiConfiguration(self::OTHER_TYPE, type: self::OTHER_TYPE_NAME);
        $this->createApiConfiguration(self::SHARED_A);
        $this->createApiConfiguration(self::SHARED_B);
    }

    /**
     * Every selector the lookup rejects: well-formed ones that select no
     * single active flowable configuration, and malformed ones.
     *
     * @return iterable<string, array{string}>
     */
    public static function rejectedSelectors(): iterable
    {
        yield from self::unmatchedSelectors();
        yield from self::malformedSelectors();
    }

    /**
     * Well-formed selectors that match no single active flowable configuration.
     *
     * @return iterable<string, array{string}>
     */
    public static function unmatchedSelectors(): iterable
    {
        yield 'inactive, prefix' => [substr(self::INACTIVE, 0, 8)];
        yield 'inactive, full' => [self::INACTIVE];
        yield 'other type, prefix' => [substr(self::OTHER_TYPE, 0, 8)];
        yield 'other type, full' => [self::OTHER_TYPE];
        yield 'ambiguous prefix' => [self::SHARED_PREFIX];
        yield 'unknown' => ['deadbeef'];
    }

    /**
     * Selectors outside the selector format (8 lowercase hex characters or a
     * full lowercase UUID).
     *
     * @return iterable<string, array{string}>
     */
    public static function malformedSelectors(): iterable
    {
        yield '7 characters' => [substr(self::SELECTED, 0, 7)];
        yield '9 characters' => [substr(self::SELECTED, 0, 9)];
        yield '35 characters' => [substr(self::SELECTED, 0, 35)];
        yield 'uppercase prefix' => [strtoupper(substr(self::SELECTED, 0, 8))];
        yield 'uppercase full' => [strtoupper(self::SELECTED)];
        yield 'percent' => ['0a1b2c3%'];
        yield 'underscore' => ['0a1b2c3_'];
        yield 'asterisk' => ['0a1b2c3*'];
        yield 'trailing newline' => [substr(self::SELECTED, 0, 8) . "\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedSelectors(): iterable
    {
        yield 'prefix' => [substr(self::SELECTED, 0, 8)];
        yield 'full' => [self::SELECTED];
    }

    // --- HTTP ------------------------------------------------------------------

    #[DataProvider('rejectedSelectors')]
    public function testHttpQueryRejectsSelector(string $selector): void
    {
        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks?apiConfiguration=' . rawurlencode($selector), ['ROLE_USER']);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        $this->assertGenericRejection((string) $response->getContent());
    }

    #[DataProvider('acceptedSelectors')]
    public function testHttpQuerySelectsTheConfiguration(string $selector): void
    {
        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks?apiConfiguration=' . $selector, ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertReachedSelected();
    }

    public function testHttpQueryTakesNoUuidObject(): void
    {
        // The {uuid} form exists for a JSON body and MCP arguments only: an
        // array in the query string is a 400 and reaches no configuration.
        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks?apiConfiguration[uuid]=' . substr(self::SELECTED, 0, 8), ['ROLE_USER']);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests);
    }

    public function testHttpBodyRejectsInactiveUuidObject(): void
    {
        $response = $this->completeTaskOverHttp(['uuid' => substr(self::INACTIVE, 0, 8)]);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        $this->assertGenericRejection((string) $response->getContent());
    }

    public function testHttpBodyRejectsUuidObjectWithWrongFormat(): void
    {
        // The {uuid} branch carries the selector pattern: the schema rejects
        // it before any lookup.
        $response = $this->completeTaskOverHttp(['uuid' => strtoupper(substr(self::SELECTED, 0, 8))]);

        self::assertSame(422, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests);
    }

    public function testHttpBodySelectsByUuidObject(): void
    {
        $response = $this->completeTaskOverHttp(['uuid' => substr(self::SELECTED, 0, 8)]);

        self::assertLessThan(300, $response->getStatusCode(), (string) $response->getContent());
        $this->assertReachedSelected();
    }

    // --- MCP -------------------------------------------------------------------

    #[DataProvider('unmatchedSelectors')]
    public function testMcpToolRejectsSelector(string $selector): void
    {
        $result = $this->mcpCallTool('flowable_list_tasks', ['apiConfiguration' => $selector], [P::roleFor(P::MCP_READ)]);

        self::assertMcpFailed($result);
        $this->assertGenericRejection(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    #[DataProvider('malformedSelectors')]
    public function testMcpToolRejectsMalformedSelectorBySchema(string $selector): void
    {
        // The read tool validates its arguments against its published schema,
        // whose selector pattern rejects these before any lookup.
        $result = $this->mcpCallTool('flowable_list_tasks', ['apiConfiguration' => $selector], [P::roleFor(P::MCP_READ)]);

        self::assertMcpFailed($result);
        $output = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertStringContainsString('Input validation failed', $output);
        self::assertStringContainsString('/apiConfiguration', $output);
        $this->assertNothingNamed($output);
        self::assertSame([], $this->engine()->requests, 'a rejected selection reached the engine');
    }

    #[DataProvider('acceptedSelectors')]
    public function testMcpToolSelectsTheConfiguration(string $selector): void
    {
        $result = $this->mcpCallTool('flowable_list_tasks', ['apiConfiguration' => $selector], [P::roleFor(P::MCP_READ)]);

        self::assertNotTrue($result['result']['isError'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
        $this->assertReachedSelected();
    }

    public function testMcpWriteToolRejectsInactiveUuidObject(): void
    {
        $result = $this->mcpCallTool(
            'flowable_complete_task',
            ['id' => 't1', 'apiConfiguration' => ['uuid' => self::INACTIVE]],
            [P::roleFor(P::TASK_COMPLETE)],
        );

        self::assertMcpFailed($result);
        $this->assertGenericRejection(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function testMcpWriteToolRejectsUuidObjectWithWrongFormat(): void
    {
        $result = $this->mcpCallTool(
            'flowable_complete_task',
            ['id' => 't1', 'apiConfiguration' => ['uuid' => substr(self::SELECTED, 0, 9)]],
            [P::roleFor(P::TASK_COMPLETE)],
        );

        self::assertMcpFailed($result);
        // Rejected by the schema of the {uuid} branch, before any lookup
        self::assertStringContainsString('/apiConfiguration/uuid', json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        self::assertSame([], $this->engine()->requests);
    }

    // --- console and worker ----------------------------------------------------

    #[DataProvider('rejectedSelectors')]
    public function testConsoleOptionRejectsSelector(string $selector): void
    {
        $tester = $this->runCommand('flowable:tasks', ['--api-configuration' => $selector]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGenericRejection(self::normalized($tester->getDisplay()));
    }

    #[DataProvider('acceptedSelectors')]
    public function testConsoleOptionSelectsTheConfiguration(string $selector): void
    {
        $tester = $this->runCommand('flowable:tasks', ['--api-configuration' => $selector]);

        self::assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertReachedSelected();
    }

    #[DataProvider('rejectedSelectors')]
    public function testWorkerStartRejectsSelector(string $selector): void
    {
        $tester = $this->runCommand('flowable:external-worker:run', [
            '--api-configuration' => $selector,
            '--topic' => ['orders'],
            '--once' => true,
        ]);

        self::assertSame(1, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertGenericRejection(self::normalized($tester->getDisplay()));
    }

    // --- implicit selection ----------------------------------------------------

    public function testImplicitSelectionIgnoresInactiveAndOtherTypes(): void
    {
        // Back to one active flowable configuration (the default one): the
        // inactive one and the one of another type do not count.
        $entityManager = $this->entityManager();
        foreach ([self::SELECTED, self::SHARED_A, self::SHARED_B] as $id) {
            $entityManager->remove($entityManager->find(ApiConfiguration::class, Uuid::fromString($id)));
        }
        $entityManager->flush();
        $entityManager->clear();

        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks', ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertNotSame([], $this->engine()->requests);
        self::assertStringStartsWith('/flowable-rest/', $this->engine()->requests[0]['path']);
    }

    // --- helpers ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $apiConfiguration
     */
    private function completeTaskOverHttp(array $apiConfiguration): Response
    {
        return $this->requestAs(
            'POST',
            self::FLOWABLE . '/tasks/t1/complete',
            [P::roleFor(P::TASK_COMPLETE)],
            ['apiConfiguration' => $apiConfiguration],
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(string $name, array $input): CommandTester
    {
        $application = new Application(static::$kernel);
        $tester = new CommandTester($application->find($name));
        $tester->execute($input);

        return $tester;
    }

    /**
     * A failed tool call: a JSON-RPC `error` or a result with `isError`.
     *
     * @param array<string, mixed> $result
     */
    private static function assertMcpFailed(array $result): void
    {
        self::assertTrue(
            isset($result['error']) || ($result['result']['isError'] ?? false) === true,
            json_encode($result, JSON_THROW_ON_ERROR),
        );
    }

    private function assertGenericRejection(string $output): void
    {
        self::assertStringContainsString(FlowableClientLocator::INVALID_SELECTION, $output);
        $this->assertNothingNamed($output);
        self::assertSame([], $this->engine()->requests, 'a rejected selection reached the engine');
    }

    /** The output names no configuration id and no other configuration type. */
    private function assertNothingNamed(string $output): void
    {
        self::assertStringNotContainsString(self::OTHER_TYPE_NAME, $output);
        foreach ([self::SELECTED, self::INACTIVE, self::OTHER_TYPE, self::SHARED_A, self::SHARED_B] as $id) {
            self::assertStringNotContainsString($id, $output);
        }
    }

    private function assertReachedSelected(): void
    {
        self::assertNotSame([], $this->engine()->requests);
        foreach ($this->engine()->requests as $request) {
            self::assertStringStartsWith(self::SELECTED_PATH, $request['path']);
        }
    }

    /** SymfonyStyle wraps and indents the error block. */
    private static function normalized(string $display): string
    {
        return (string) preg_replace('/\s+/', ' ', $display);
    }
}
// - revised 2026-11-26 (malformed selectors over MCP are rejected by the read tool schema)
// - revised 2026-11-26 (an array selector in the query string is a 400)
