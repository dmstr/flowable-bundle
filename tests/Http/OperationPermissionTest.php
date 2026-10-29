<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Every HTTP operation checks its own `flowable:*` permission: the single
 * role of that permission passes, the single roles of all twelve others do
 * not. A permission therefore implies no other, e.g. `flowable:read` not
 * `flowable:mcp_read`, `flowable:deploy` not `flowable:deploy_delete`.
 */
final class OperationPermissionTest extends ApiTestCase
{
    use OperationRequests;

    /**
     * @return iterable<string, array{string, string, string, ?array<string, mixed>, ?string}>
     */
    public static function sensitiveOperations(): iterable
    {
        yield from PermissionCatalog::guardedSensitiveOperations();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readUris(): iterable
    {
        foreach (PermissionCatalog::guardedReadUris() as $uri) {
            yield $uri => [$uri];
        }
    }

    /**
     * @param array{string, string, string, ?array<string, mixed>, ?string} $operation
     */
    #[DataProvider('sensitiveOperations')]
    public function testSensitiveOperationNeedsExactlyItsPermission(string $method, string $uri, string $permission, ?array $body, ?string $upload): void
    {
        $operation = [$method, $uri, $permission, $body, $upload];

        $denied = $this->perform($operation, P::rolesExcept($permission));
        self::assertSame(403, $denied->getStatusCode(), (string) $denied->getContent());
        self::assertSame([], $this->engine()->requests, 'a denied operation reached the engine');

        $allowed = $this->perform($operation, [P::roleFor($permission)]);
        self::assertLessThan(400, $allowed->getStatusCode(), (string) $allowed->getContent());
        self::assertNotSame([], $this->engine()->requests, 'an allowed operation did not reach the engine');
    }

    /**
     * @param array{string, string, string, ?array<string, mixed>, ?string} $operation
     */
    #[DataProvider('sensitiveOperations')]
    public function testSensitiveOperationRequiresAuthentication(string $method, string $uri, string $permission, ?array $body, ?string $upload): void
    {
        $response = $this->requestAnonymous($method, $uri, $body);

        self::assertSame(401, $response->getStatusCode(), (string) $response->getContent());
    }

    #[DataProvider('sensitiveOperations')]
    public function testAdminRoleOfTheExampleMapPassesEverything(string $method, string $uri, string $permission, ?array $body, ?string $upload): void
    {
        $response = $this->perform([$method, $uri, $permission, $body, $upload], ['ROLE_FLOWABLE_ADMIN']);

        self::assertLessThan(400, $response->getStatusCode(), (string) $response->getContent());
    }

    #[DataProvider('readUris')]
    public function testReadOperationNeedsFlowableRead(string $uri): void
    {
        $denied = $this->requestAs('GET', $uri, P::rolesExcept(P::READ));
        self::assertSame(403, $denied->getStatusCode(), (string) $denied->getContent());
        self::assertSame([], $this->engine()->requests);

        $allowed = $this->requestAs('GET', $uri, [P::roleFor(P::READ)]);
        self::assertLessThan(400, $allowed->getStatusCode(), (string) $allowed->getContent());
        self::assertNotSame([], $this->engine()->requests);
    }

    public function testDeadLetterListNeedsFlowableReadNotMcpRead(): void
    {
        $uri = self::FLOWABLE . '/jobs?kind=deadletter';

        self::assertSame(403, $this->requestAs('GET', $uri, [P::roleFor(P::MCP_READ)])->getStatusCode());
        self::assertSame(200, $this->requestAs('GET', $uri, [P::roleFor(P::READ)])->getStatusCode());
        self::assertContains(
            ['method' => 'GET', 'path' => '/flowable-rest/service/management/deadletter-jobs'],
            $this->engine()->requests,
        );
    }

    public function testRoleUserOfTheExampleMapOnlyReads(): void
    {
        foreach (PermissionCatalog::guardedSensitiveOperations() as $name => [$method, $uri, , $body, $upload]) {
            $response = $this->perform([$method, $uri, '', $body, $upload], ['ROLE_USER']);
            self::assertSame(403, $response->getStatusCode(), $name);
        }
    }
}
