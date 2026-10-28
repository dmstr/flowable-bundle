<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestKernel;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The bundle ships no voter. In an application that grants none of the
 * `flowable:*` attributes (kernel variant without a voter) every operation
 * and every tool is denied, whatever roles the user has.
 */
final class NoVoterDeniesEverythingTest extends ApiTestCase
{
    use OperationRequests;

    protected static function kernelEnvironment(): string
    {
        return TestKernel::ENV_NO_VOTER;
    }

    /**
     * @return list<string> every role of the example map, so only a missing voter can deny
     */
    private static function allRoles(): array
    {
        return ['ROLE_USER', 'ROLE_FLOWABLE_ADMIN', ...array_map(P::roleFor(...), P::ALL)];
    }

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

    #[DataProvider('sensitiveOperations')]
    public function testSensitiveOperationIsDenied(string $method, string $uri, string $permission, ?array $body, ?string $upload): void
    {
        $response = $this->perform([$method, $uri, $permission, $body, $upload], self::allRoles());

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests);
    }

    #[DataProvider('readUris')]
    public function testReadOperationIsDenied(string $uri): void
    {
        $response = $this->requestAs('GET', $uri, self::allRoles());

        self::assertSame(403, $response->getStatusCode(), (string) $response->getContent());
        self::assertSame([], $this->engine()->requests);
    }

    public function testNoToolIsVisible(): void
    {
        self::assertSame([], $this->mcpToolNames(self::allRoles()));
    }

    public function testNoToolIsCallable(): void
    {
        foreach (PermissionCatalog::tools() as $name => [, $arguments]) {
            $result = $this->mcpCallTool($name, $arguments, self::allRoles());

            self::assertTrue(
                isset($result['error']) || ($result['result']['isError'] ?? false) === true,
                $name . ': ' . json_encode($result, JSON_THROW_ON_ERROR),
            );
        }
        self::assertSame([], $this->engine()->requests);
    }
}
