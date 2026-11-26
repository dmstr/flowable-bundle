<?php
// file generated with AI assistance: Claude Code - 2026-11-26 12:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The id rule of the real FlowableClient, seen through HTTP and MCP: an id
 * outside the rule is a client error and no request reaches the engine, a
 * valid one goes through.
 */
final class EngineIdRuleTest extends ApiTestCase
{
    /**
     * Ids as they arrive after URL decoding, with their path-encoded form.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidPathIds(): iterable
    {
        yield 'dot-dot' => ['%2e%2e'];
        yield 'dot' => ['%2e'];
        yield 'literal %2e%2e' => ['%252e%252e'];
        yield 'unicode' => ['t%C3%A4sk'];
        yield 'leading dot' => ['.abc'];
    }

    #[DataProvider('invalidPathIds')]
    public function testHttpRejectsAnInvalidIdWithoutEngineRequest(string $encodedId): void
    {
        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks/' . $encodedId, [P::roleFor(P::READ)]);

        self::assertSame(400, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString('Invalid Flowable id', (string) $response->getContent());
        self::assertSame([], $this->engine()->requests, 'a rejected id reached the engine');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validIds(): iterable
    {
        yield 'numeric' => ['12345'];
        yield 'uuid' => ['8f14e45f-ceea-467f-a0e6-1e1a5f5f3c2b'];
        yield 'process definition id' => ['proc:1:42'];
    }

    #[DataProvider('validIds')]
    public function testHttpPassesAValidId(string $id): void
    {
        $response = $this->requestAs('GET', self::FLOWABLE . '/tasks/' . $id, [P::roleFor(P::READ)]);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertCount(1, $this->engine()->requests);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidToolIds(): iterable
    {
        yield 'dot-dot' => ['..'];
        yield 'dot' => ['.'];
        yield 'slash' => ['a/b'];
        yield 'encoded dot-dot' => ['%2e%2e'];
        yield 'unicode' => ['täsk'];
        yield 'trailing newline' => ["p1\n"];
    }

    #[DataProvider('invalidToolIds')]
    public function testMcpToolRejectsAnInvalidIdWithoutEngineRequest(string $id): void
    {
        $result = $this->mcpCallTool('flowable_get_process_status', ['id' => $id], [P::roleFor(P::MCP_READ)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, $encoded);
        self::assertStringContainsString('Invalid Flowable id', $encoded);
        self::assertSame([], $this->engine()->requests, 'a rejected id reached the engine');
    }

    #[DataProvider('validIds')]
    public function testMcpToolPassesAValidId(string $id): void
    {
        $result = $this->mcpCallTool('flowable_get_process_status', ['id' => $id], [P::roleFor(P::MCP_READ)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('result', $result, $encoded);
        self::assertNotTrue($result['result']['isError'] ?? false, $encoded);
        self::assertNotSame([], $this->engine()->requests);
    }
}
