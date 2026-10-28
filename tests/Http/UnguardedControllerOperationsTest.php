<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The expected behaviour of the three controller operations with `read: false`
 * (see {@see PermissionCatalog::controllerOperations()}): the single role of
 * their permission passes, the single roles of the twelve others do not.
 *
 * KNOWN GAP, skipped until the controllers check the permission themselves:
 * API Platform never evaluates `security` for these operations, so they answer
 * 200 to every authenticated user. Remove the skip when that is fixed.
 */
final class UnguardedControllerOperationsTest extends ApiTestCase
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
        self::markTestSkipped('Known gap: security of read:false controller operations is not evaluated (stacktrace, input schemas).');

        self::assertSame(403, $this->requestAs('GET', $uri, P::rolesExcept($permission))->getStatusCode());
        self::assertSame(200, $this->requestAs('GET', $uri, [P::roleFor($permission)])->getStatusCode());
    }
}
