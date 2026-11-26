<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:41:17 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The six read tools and flowable_dmn_deploy take `apiConfiguration` as a
 * selector string or as `{uuid}`, like the other write tools, and select the
 * named configuration; a `{uuid}` the schema does not allow is rejected
 * before the engine is asked. Seen through real `tools/call` requests of a
 * user holding exactly the tool's permission.
 *
 * Over HTTP the read endpoints take `apiConfiguration` from the query string
 * as before; values the query string cannot turn into a selector string end
 * in a 400, never in the default connection.
 *
 * Which configuration a call used is told by the engine path: requests to the
 * selected configuration land under {@see self::SELECTED_PATH}, those to the
 * default one of {@see ApiTestCase::setUp()} under `/flowable-rest/`.
 */
final class ToolApiConfigurationFormTest extends ApiTestCase
{
    private const string SELECTED = '0a1b2c3d-1111-4111-8111-111111111111';
    private const string SELECTED_PATH = '/selected-rest/';
    private const string DEFAULT_PATH = '/flowable-rest/';

    private const string DMN = '<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/"/>';

    /**
     * Tool, its arguments besides `apiConfiguration`, the permission it needs.
     *
     * @return array<string, array{string, array<string, mixed>, string}>
     */
    private static function tools(): array
    {
        return [
            'flowable_list_process_definitions' => ['flowable_list_process_definitions', [], P::MCP_READ],
            'flowable_list_tasks' => ['flowable_list_tasks', [], P::MCP_READ],
            'flowable_get_task_form' => ['flowable_get_task_form', ['id' => 't1'], P::MCP_READ],
            'flowable_get_process_status' => ['flowable_get_process_status', ['id' => 'p1'], P::MCP_READ],
            'flowable_history_get' => ['flowable_history_get', ['id' => 'p1'], P::MCP_READ],
            'flowable_system_list_deadletter_jobs' => ['flowable_system_list_deadletter_jobs', [], P::MCP_READ],
            'flowable_dmn_deploy' => ['flowable_dmn_deploy', ['name' => 'rates.dmn', 'content' => self::DMN], P::DEPLOY],
        ];
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string, mixed}>
     */
    public static function acceptedSelections(): iterable
    {
        $selections = [
            'string, prefix' => substr(self::SELECTED, 0, 8),
            'string, full' => self::SELECTED,
            '{uuid}, prefix' => ['uuid' => substr(self::SELECTED, 0, 8)],
            '{uuid}, full' => ['uuid' => self::SELECTED],
        ];
        foreach (self::tools() as $name => [$tool, $arguments, $permission]) {
            foreach ($selections as $label => $selection) {
                yield $name . ' ' . $label => [$tool, $arguments, $permission, $selection];
            }
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('acceptedSelections')]
    public function testToolUsesTheSelectedConfiguration(string $tool, array $arguments, string $permission, mixed $selection): void
    {
        $this->createSelectedConfiguration();

        $result = $this->mcpCallTool($tool, $arguments + ['apiConfiguration' => $selection], [P::roleFor($permission)]);

        $this->assertSucceeded($result);
        $this->assertReached(self::SELECTED_PATH);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string, mixed, string}>
     */
    public static function invalidObjects(): iterable
    {
        $objects = [
            '{"uuid": 5}' => [['uuid' => 5], '/apiConfiguration'],
            '{}' => [new \stdClass(), '/apiConfiguration'],
            '{"uuid": <valid>, "extra": 1}' => [['uuid' => self::SELECTED, 'extra' => 1], '/apiConfiguration'],
        ];
        foreach (self::tools() as $name => [$tool, $arguments, $permission]) {
            foreach ($objects as $label => [$object, $path]) {
                yield $name . ' ' . $label => [$tool, $arguments, $permission, $object, $path];
            }
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('invalidObjects')]
    public function testToolRejectsAnObjectOutsideTheSchema(string $tool, array $arguments, string $permission, mixed $object, string $path): void
    {
        $this->createSelectedConfiguration();

        $result = $this->mcpCallTool($tool, $arguments + ['apiConfiguration' => $object], [P::roleFor($permission)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $encoded);
        self::assertStringContainsString('Input validation failed', $encoded);
        self::assertStringContainsString($path, $encoded);
        self::assertSame([], $this->engine()->requests, 'a rejected call reached the engine');
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function allTools(): iterable
    {
        yield from self::tools();
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('allTools')]
    public function testToolWithoutSelectionUsesTheDefaultConfiguration(string $tool, array $arguments, string $permission): void
    {
        $result = $this->mcpCallTool($tool, $arguments, [P::roleFor($permission)]);

        $this->assertSucceeded($result);
        $this->assertReached(self::DEFAULT_PATH);
    }

    // --- HTTP ------------------------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function httpReads(): iterable
    {
        yield 'process definitions' => ['/process_definitions'];
        yield 'tasks' => ['/tasks'];
        yield 'process instance' => ['/process_instances/p1'];
        yield 'historic process instance' => ['/historic_process_instances/p1'];
        yield 'jobs' => ['/jobs?kind=deadletter'];
    }

    #[DataProvider('httpReads')]
    public function testHttpQueryStringSelectsAsBefore(string $uri): void
    {
        $this->createSelectedConfiguration();

        foreach ([substr(self::SELECTED, 0, 8), self::SELECTED] as $selector) {
            $response = $this->requestAs('GET', self::withQuery($uri, 'apiConfiguration=' . $selector), [P::roleFor(P::READ)]);

            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            $this->assertReached(self::SELECTED_PATH);
            $this->engine()->requests = [];
        }
    }

    #[DataProvider('httpReads')]
    public function testHttpEmptyQueryValueIsNoSelection(string $uri): void
    {
        $response = $this->requestAs('GET', self::withQuery($uri, 'apiConfiguration='), [P::roleFor(P::READ)]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $this->assertReached(self::DEFAULT_PATH);
    }

    /**
     * Query strings PHP decodes into an array. They were a 400 before (the
     * request's InputBag refuses a non-scalar value) and stay one: the
     * `{uuid}` form is MCP and JSON body only.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function httpArrayQueries(): iterable
    {
        foreach (self::httpReads() as $name => [$uri]) {
            yield $name . ' apiConfiguration[]' => [$uri, 'apiConfiguration[]=' . substr(self::SELECTED, 0, 8)];
            yield $name . ' apiConfiguration[uuid]' => [$uri, 'apiConfiguration[uuid]=' . substr(self::SELECTED, 0, 8)];
        }
    }

    #[DataProvider('httpArrayQueries')]
    public function testHttpArrayQueryValueIsRejected(string $uri, string $query): void
    {
        $this->createSelectedConfiguration();

        $response = $this->requestAs('GET', self::withQuery($uri, $query), [P::roleFor(P::READ)]);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests, 'a rejected selection reached the engine');
    }

    // --- helpers ---------------------------------------------------------------

    /**
     * A second active configuration next to the default one; with both, a
     * call without selection would end in a 400, so a success proves the
     * selection.
     */
    private function createSelectedConfiguration(): void
    {
        $this->createApiConfiguration(
            self::SELECTED,
            baseUrl: 'http://flowable.invalid' . rtrim(self::SELECTED_PATH, '/'),
        );
    }

    private static function withQuery(string $uri, string $query): string
    {
        return self::FLOWABLE . $uri . (str_contains($uri, '?') ? '&' : '?') . $query;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function assertSucceeded(array $result): void
    {
        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('result', $result, $encoded);
        self::assertNotTrue($result['result']['isError'] ?? false, $encoded);
        self::assertStringNotContainsString(FlowableClientLocator::INVALID_SELECTION, $encoded);
    }

    /** Every engine request went to the configuration under $path. */
    private function assertReached(string $path): void
    {
        self::assertNotSame([], $this->engine()->requests);
        foreach ($this->engine()->requests as $request) {
            self::assertStringStartsWith($path, $request['path']);
        }
    }
}
