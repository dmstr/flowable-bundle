<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestKernel;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter;

/**
 * The kernel variant without any custom voter boots and serves requests.
 */
final class NoVoterKernelSmokeTest extends ApiTestCase
{
    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_NO_VOTER;
    }

    public function testNoPermissionVoterIsRegistered(): void
    {
        self::assertFalse(static::getContainer()->has(TestPermissionVoter::class));
    }

    public function testCollectionStillRequiresAuthentication(): void
    {
        self::assertSame(401, $this->requestAnonymous('GET', self::FLOWABLE . '/process_definitions')->getStatusCode());
    }
}
