<?php
// file generated with AI assistance: Claude Code - 2026-11-26 16:02:34 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Exception\FlowableApiException;
use Dmstr\Flowable\Tests\Kernel\TestFlowableEngine;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The shape of `tools/call` answers over the real MCP transport, as the tool
 * descriptions and the README state it:
 *
 *  - the four write tools that return null answer a success with a result
 *    without structuredContent, and a failure with a JSON-RPC error
 *    (-32603, API Platform's MCP handler turns the HTTP exception into
 *    one), not with a result carrying `isError`;
 *  - structuredContent is the normalisation in the tool's output format,
 *    here API Platform's first format `jsonld`, and the published
 *    outputSchema is built for the same format; for an output class that is
 *    no API resource (e.g. the status of flowable_get_process_status) the
 *    schema names the plain fields only, while structuredContent also
 *    carries `@context`, `@id` and `@type`.
 */
final class McpToolResultShapeTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, array<string, mixed>, string}> tool, arguments, permission
     */
    public static function nullTools(): iterable
    {
        yield 'flowable_complete_task' => ['flowable_complete_task', ['id' => 't1'], P::TASK_COMPLETE];
        yield 'flowable_system_trigger_execution' => ['flowable_system_trigger_execution', ['id' => 'e1'], P::EXECUTION_TRIGGER];
        yield 'flowable_system_execute_timer_job' => ['flowable_system_execute_timer_job', ['id' => 'timer-1'], P::JOB_EXECUTE];
        yield 'flowable_events_send' => ['flowable_events_send', [
            'eventDefinitionKey' => 'orderPaid',
            'channelDefinitionKey' => 'inbound',
            'eventPayload' => ['orderId' => '42'],
        ], P::EVENT_SEND];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('nullTools')]
    public function testFailureIsAJsonRpcError(string $tool, array $arguments, string $permission): void
    {
        $this->engine()->failWith(500, TestFlowableEngine::errorBody('NPE in engine.internal'));

        $result = $this->mcpCallTool($tool, $arguments, [P::roleFor($permission)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayNotHasKey('result', $result, $encoded);
        self::assertSame(-32603, $result['error']['code'] ?? null, $encoded);
        self::assertMatchesRegularExpression(
            '/\A' . str_replace('%s', '[0-9a-f]{16}', preg_quote(FlowableApiException::BACKEND_ERROR, '/')) . '\z/',
            (string) $result['error']['message'],
        );
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('nullTools')]
    public function testSuccessIsAResultWithoutStructuredContent(string $tool, array $arguments, string $permission): void
    {
        $result = $this->mcpCallTool($tool, $arguments, [P::roleFor($permission)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayNotHasKey('error', $result, $encoded);
        self::assertFalse($result['result']['isError'] ?? null, $encoded);
        self::assertArrayNotHasKey('structuredContent', $result['result'], $encoded);
    }

    public function testRunNowSendsTheMoveActionForTheTimerJob(): void
    {
        $result = $this->mcpCallTool('flowable_system_execute_timer_job', ['id' => 'timer-1'], [P::roleFor(P::JOB_EXECUTE)]);

        self::assertArrayNotHasKey('error', $result, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertSame([['method' => 'POST', 'path' => '/flowable-rest/service/management/timer-jobs/timer-1']], $this->engine()->requests);
        self::assertSame('{"action":"move"}', $this->engine()->bodies[0]);
    }

    public function testStructuredContentCarriesJsonLdKeysTheOutputSchemaOfAnOutputClassDoesNotName(): void
    {
        $roles = [P::roleFor(P::MCP_READ)];
        $result = $this->mcpCallTool('flowable_get_process_status', ['id' => 'pi-1'], $roles);
        $content = $result['result']['structuredContent'] ?? null;
        self::assertIsArray($content, json_encode($result, JSON_THROW_ON_ERROR));

        $schema = $this->outputSchema('flowable_get_process_status', $roles);

        self::assertSame('FlowProcessStatus', $content['@type'] ?? null);
        self::assertArrayHasKey('@context', $content);
        self::assertArrayHasKey('@id', $content);
        $schemaKeys = array_keys($schema['properties']);
        self::assertSame(['processInstance', 'openTasks', 'openTasksTotal', 'currentActivityIds', 'activeExecutions'], $schemaKeys);
        self::assertSame(['@context', '@type', '@id'], array_values(array_diff(array_keys($content), $schemaKeys)));
        self::assertArrayNotHasKey('additionalProperties', $schema, 'extra keys are not forbidden by the schema');
        // The text content is the same normalisation, encoded
        self::assertSame($content, json_decode($result['result']['content'][0]['text'], true, flags: JSON_THROW_ON_ERROR));
    }

    public function testOutputSchemaOfAResourceIsTheJsonLdSchema(): void
    {
        $schema = $this->outputSchema('flowable_dmn_evaluate', [P::roleFor(P::DECISION_EVALUATE)]);

        self::assertArrayHasKey('@context', $schema['properties']);
        self::assertArrayHasKey('@id', $schema['properties']);
        self::assertArrayHasKey('@type', $schema['properties']);
        self::assertSame(['@type'], $schema['required']);
        // FINDING (not fixed here): the rows of `result` are JSON arrays (a
        // list of result variables, see DecisionEvaluateResultTest), but the
        // schema derived from the property's `list<array<string,mixed>>`
        // declares each item an object, so a client that validates
        // structuredContent strictly rejects a non-empty result.
        self::assertSame('object', $schema['properties']['result']['items']['type'] ?? null);
    }

    /**
     * @param list<string> $roles
     * @return array<string, mixed>
     */
    private function outputSchema(string $tool, array $roles): array
    {
        $list = $this->mcpRequest('tools/list', [], $roles);
        foreach ($list['result']['tools'] ?? [] as $entry) {
            if ($entry['name'] === $tool) {
                self::assertIsArray($entry['outputSchema'] ?? null, $tool . ' publishes no outputSchema');

                return $entry['outputSchema'];
            }
        }
        self::fail($tool . ' is not listed');
    }
}
