<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The three controller operations with `read: false` (see
 * {@see PermissionCatalog::controllerOperations()}) check their permission in
 * the controller, because API Platform does not evaluate their `security`
 * expression: the single role of their permission passes, the single roles of
 * the twelve others do not, a denied request never reaches the engine, and an
 * anonymous request gets 401.
 */
final class ControllerOperationPermissionTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function operations(): iterable
    {
        foreach (PermissionCatalog::controllerOperations() as $name => [$uri, $permission]) {
            yield $name => [$uri, $permission];
        }
    }

    #[DataProvider('operations')]
    public function testOperationNeedsExactlyItsPermission(string $uri, string $permission): void
    {
        $denied = $this->requestAs('GET', $uri, P::rolesExcept($permission));
        self::assertSame(403, $denied->getStatusCode(), (string) $denied->getContent());
        self::assertSame([], $this->engine()->requests, 'a denied operation reached the engine');

        $allowed = $this->requestAs('GET', $uri, [P::roleFor($permission)]);
        self::assertSame(200, $allowed->getStatusCode(), (string) $allowed->getContent());
    }

    #[DataProvider('operations')]
    public function testOperationRequiresAuthentication(string $uri, string $permission): void
    {
        $response = $this->requestAnonymous('GET', $uri);

        self::assertSame(401, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests);
    }
}
// - revised 2026-10-28 (renamed from UnguardedControllerOperationsTest, the gap is fixed in the controllers)
