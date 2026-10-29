<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestKernel;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every MCP tool checks its own `flowable:*` permission: it is listed and
 * callable with the single role of that permission, and neither listed nor
 * callable with the single roles of the twelve others.
 *
 * "Permission present but the mcp.* switch off" is a metadata matter (the
 * tool is removed from the resource metadata, so it exists for no one); the
 * test kernel switches all three on, see
 * {@see \Dmstr\Flowable\Tests\Mcp\McpToolMetadataTest::testSwitchesKeepTheExpectedTools()}.
 */
final class McpToolPermissionTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function tools(): iterable
    {
        foreach (PermissionCatalog::tools() as $name => [$permission, $arguments]) {
            yield $name => [$name, $permission, $arguments];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function permissions(): iterable
    {
        foreach (P::ALL as $permission) {
            yield $permission => [$permission];
        }
    }

    #[DataProvider('permissions')]
    public function testSinglePermissionListsExactlyItsTools(string $permission): void
    {
        self::assertSame(PermissionCatalog::toolsOf($permission), $this->mcpToolNames([P::roleFor($permission)]));
    }

    #[DataProvider('tools')]
    public function testToolIsHiddenWithoutItsPermission(string $name, string $permission, array $arguments): void
    {
        self::assertNotContains($name, $this->mcpToolNames(P::rolesExcept($permission)));
    }

    #[DataProvider('tools')]
    public function testToolCallIsRefusedWithoutItsPermission(string $name, string $permission, array $arguments): void
    {
        $result = $this->mcpCallTool($name, $arguments, P::rolesExcept($permission));

        self::assertTrue(
            isset($result['error']) || ($result['result']['isError'] ?? false) === true,
            json_encode($result, JSON_THROW_ON_ERROR),
        );
        self::assertSame([], $this->engine()->requests, 'a refused tool call reached the engine');
    }

    #[DataProvider('tools')]
    public function testToolCallSucceedsWithItsPermission(string $name, string $permission, array $arguments): void
    {
        $result = $this->mcpCallTool($name, $arguments, [P::roleFor($permission)]);

        self::assertArrayHasKey('result', $result, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertNotTrue($result['result']['isError'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertNotSame([], $this->engine()->requests);
    }

    public function testDeadLetterToolNeedsMcpReadNotRead(): void
    {
        $tool = 'flowable_system_list_deadletter_jobs';

        self::assertNotContains($tool, $this->mcpToolNames([P::roleFor(P::READ)]));
        self::assertContains($tool, $this->mcpToolNames([P::roleFor(P::MCP_READ)]));
    }

    public function testAdminRoleListsAllFourteenTools(): void
    {
        self::assertCount(14, PermissionCatalog::tools());
        $expected = array_keys(PermissionCatalog::tools());
        sort($expected);
        self::assertSame($expected, $this->mcpToolNames(['ROLE_FLOWABLE_ADMIN']));
        self::assertSame([], $this->mcpToolNames(['ROLE_USER']));
    }

    public function testAnonymousSeesAndCallsNothing(): void
    {
        // The test firewall has no access_control, so the transport itself
        // answers; what matters is that the tool checks deny an anonymous user.
        $session = $this->anonymousMcp('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
        ]);
        $sessionId = (string) $session->headers->get('Mcp-Session-Id', '');

        $list = $this->anonymousMcp('tools/list', [], $sessionId);
        if ($list->getStatusCode() === 200) {
            self::assertSame([], self::decode($list)['result']['tools'] ?? []);
        } else {
            self::assertSame(401, $list->getStatusCode());
        }

        $call = $this->anonymousMcp('tools/call', ['name' => 'flowable_list_tasks', 'arguments' => new \stdClass()], $sessionId);
        self::assertContains($call->getStatusCode(), [200, 401]);
        if ($call->getStatusCode() === 200) {
            $result = self::decode($call);
            self::assertTrue(isset($result['error']) || ($result['result']['isError'] ?? false) === true, json_encode($result, JSON_THROW_ON_ERROR));
        }
        self::assertSame([], $this->engine()->requests);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function anonymousMcp(string $method, array $params, ?string $sessionId = null): Response
    {
        $headers = ['Accept' => 'application/json, text/event-stream', 'Content-Type' => 'application/json'];
        if ($sessionId !== null) {
            $headers['Mcp-Session-Id'] = $sessionId;
        }

        return $this->requestAnonymous('POST', TestKernel::MCP_PATH, [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => $method,
            'params' => $params === [] ? new \stdClass() : $params,
        ], $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(Response $response): array
    {
        $body = (string) $response->getContent();
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
            foreach (explode("\n", $body) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $body = trim(substr($line, 5));
                    break;
                }
            }
        }

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }
}
