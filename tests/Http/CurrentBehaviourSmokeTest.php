<?php
// file generated with AI assistance: Claude Code - 2026-10-28 21:15:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter;

/**
 * Smoke test of the HTTP and MCP test infrastructure against TODAY's
 * behaviour: the operations are still guarded by role expressions
 * (`ROLE_USER` for reading, `ROLE_FLOWABLE_ADMIN` for writing and for every
 * MCP tool). The full permission matrix comes with the switch to `flowable:*`
 * permission attributes.
 */
final class CurrentBehaviourSmokeTest extends ApiTestCase
{
    private const string DEFINITIONS = self::FLOWABLE . '/process_definitions';

    public function testCollectionIsReadableForARoleUser(): void
    {
        $response = $this->requestAs('GET', self::DEFINITIONS, ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(0, self::json($response)['totalItems'] ?? null);
        self::assertContains(
            ['method' => 'GET', 'path' => '/flowable-rest/service/repository/process-definitions'],
            $this->engine()->requests,
        );
    }

    public function testItemReachesTheProvider(): void
    {
        $response = $this->requestAs('GET', self::DEFINITIONS . '/def-1', ['ROLE_USER']);

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame('def-1', self::json($response)['id'] ?? null);
    }

    public function testCollectionRequiresAuthentication(): void
    {
        self::assertSame(401, $this->requestAnonymous('GET', self::DEFINITIONS)->getStatusCode());
        // No test header at all is anonymous too
        self::assertSame(401, $this->request('GET', self::DEFINITIONS)->getStatusCode());
    }

    public function testWriteIsForbiddenForARoleUser(): void
    {
        $response = $this->requestAs('POST', self::DEFINITIONS . '/def-1/start', ['ROLE_USER'], []);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testWriteIsAllowedForTheAdminRole(): void
    {
        $response = $this->requestAs('POST', self::DEFINITIONS . '/def-1/start', ['ROLE_FLOWABLE_ADMIN'], []);

        self::assertNotSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame(201, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testToolsAreListedForTheAdminRoleOnly(): void
    {
        $admin = $this->mcpToolNames(['ROLE_FLOWABLE_ADMIN']);

        self::assertContains('flowable_list_process_definitions', $admin);
        self::assertContains('flowable_start_process', $admin);
        self::assertSame([], $this->mcpToolNames(['ROLE_USER']));
    }

    public function testToolCallSucceedsForTheAdminRole(): void
    {
        $result = $this->mcpCallTool('flowable_list_process_definitions', [], ['ROLE_FLOWABLE_ADMIN']);

        self::assertArrayHasKey('result', $result, json_encode($result, JSON_THROW_ON_ERROR));
        self::assertNotTrue($result['result']['isError'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
    }

    public function testPermissionVoterIsRegistered(): void
    {
        self::assertTrue(static::getContainer()->has(TestPermissionVoter::class));
    }
}
