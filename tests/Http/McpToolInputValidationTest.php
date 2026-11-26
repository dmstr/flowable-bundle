<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use ApiPlatform\State\Provider\ReadProvider;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Symfony\Security\State\AccessCheckerProvider;
use Dmstr\Flowable\State\McpToolInputValidationProvider;
use Dmstr\Flowable\Tests\Fixtures\ForeignMcp\ForeignMcpTool;
use Dmstr\Flowable\Tests\Fixtures\ForeignMcp\ForeignMcpToolProvider;
use Dmstr\Flowable\Tests\Kernel\NonValidatingProcessDefinitionProvider;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The central validation of the bundle's MCP tool arguments
 * (State\McpToolInputValidationProvider, hooked in by
 * DependencyInjection\McpToolInputValidationPass) in the real kernel, in both
 * layouts of API Platform's provider chain:
 *
 *  - a bundle tool whose provider the application replaced by one that
 *    validates nothing is still validated;
 *  - a tool of the application is not, even when it declares a schema;
 *  - plain HTTP requests are not validated by it;
 *  - the decorator sits between the `pre_read` access check and the
 *    ReadProvider;
 *  - without API Platform's MCP support the container builds without it.
 *
 * Runs on the `mcp_fixtures` kernel ({@see \Dmstr\Flowable\Tests\Kernel\TestKernel}).
 */
final class McpToolInputValidationTest extends ApiTestCase
{
    private const string LIST_TOOL = 'flowable_list_process_definitions';

    private const array FIXTURES = ['mcp_fixtures' => true];

    protected static function kernelOptions(): array
    {
        return self::FIXTURES;
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function modes(): iterable
    {
        yield 'default chain' => [[]];
        yield 'use_symfony_listeners' => [['use_symfony_listeners' => true]];
    }

    /**
     * @param array<string, mixed> $mode
     */
    #[DataProvider('modes')]
    public function testReplacedBundleProviderIsStillValidated(array $mode): void
    {
        $this->useMode($mode);

        $rejected = $this->mcpCallTool(self::LIST_TOOL, ['page' => '2'], [P::roleFor(P::MCP_READ)]);
        $encoded = json_encode($rejected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertTrue(isset($rejected['error']) || ($rejected['result']['isError'] ?? false) === true, $encoded);
        self::assertStringContainsString('Input validation failed: {\"/page\":[\"The data (string) must match the type: integer\"]}', $encoded);
        self::assertSame([], $this->replacement()->calls, 'an invalid call reached the replaced provider');

        $accepted = $this->mcpCallTool(self::LIST_TOOL, ['latest' => true], [P::roleFor(P::MCP_READ)]);
        $encoded = json_encode($accepted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('result', $accepted, $encoded);
        self::assertNotTrue($accepted['result']['isError'] ?? false, $encoded);
        self::assertStringContainsString(NonValidatingProcessDefinitionProvider::CANNED_KEY, $encoded);
        self::assertSame([['operation' => self::LIST_TOOL, 'mcp_data' => ['latest' => true]]], $this->replacement()->calls);
        self::assertSame([], $this->engine()->requests, 'the replacement answers, not the engine');
    }

    /**
     * @param array<string, mixed> $mode
     */
    #[DataProvider('modes')]
    public function testToolOfTheApplicationIsNotValidated(array $mode): void
    {
        $this->useMode($mode);
        $arguments = ['count' => 'not-a-number', 'unknown' => true];

        $result = $this->mcpCallTool(ForeignMcpTool::TOOL, $arguments, [P::roleFor(P::MCP_READ)]);

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('result', $result, $encoded);
        self::assertNotTrue($result['result']['isError'] ?? false, $encoded);
        self::assertSame([$arguments], $this->foreignProvider()->calls);
    }

    /**
     * Symfony applies the decorator with the highest priority first, so with
     * 20 the validation wraps the ReadProvider directly, and API Platform's
     * `pre_read` check (10) and the check after reading (0) wrap it.
     *
     * @param array<string, mixed> $mode
     */
    #[DataProvider('modes')]
    public function testValidationRunsAfterThePreReadCheckAndBeforeReading(array $mode): void
    {
        $this->useMode($mode);

        $handler = static::getContainer()->get('api_platform.mcp.handler');
        $chain = self::chain((new \ReflectionProperty($handler, 'provider'))->getValue($handler));

        $at = array_search(McpToolInputValidationProvider::class, array_column($chain, 0), true);
        self::assertIsInt($at, 'the decorator is not in the MCP provider chain: ' . json_encode($chain));
        self::assertSame([AccessCheckerProvider::class, null], $chain[$at - 2], 'outside: the access check after reading');
        self::assertSame([AccessCheckerProvider::class, 'pre_read'], $chain[$at - 1], 'outside: the pre_read access check');
        self::assertSame([ReadProvider::class, null], $chain[$at + 1], 'inside: the ReadProvider');
        self::assertCount(1, array_keys(array_column($chain, 0), McpToolInputValidationProvider::class, true));
    }

    /**
     * Write tools pass the read chain as well, so their URI variables, which
     * the processor's own validation of the body never sees, are checked
     * against the published schema first. Before the central validation an
     * integer id reached the engine.
     */
    public function testWriteToolIsValidatedAgainstItsPublishedSchemaFirst(): void
    {
        foreach ([
            [['id' => 5], 'Input validation failed: {\"/id\":[\"The data (integer) must match the type: string\"]}'],
            [[], 'Input validation failed: {\"/\":[\"The required properties (id) are missing\"]}'],
        ] as [$arguments, $message]) {
            $result = $this->mcpCallTool('flowable_complete_task', $arguments, [P::roleFor(P::TASK_COMPLETE)]);
            $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            self::assertArrayHasKey('error', $result, $encoded);
            self::assertStringContainsString($message, $encoded);
        }
        self::assertSame([], $this->engine()->requests, 'a rejected call reached the engine');
        self::assertSame([], $this->flowableLog()->records(), 'a call rejected before reading is not audited');
    }

    public function testHttpReadWithOddQueryParametersIsNotValidated(): void
    {
        $response = $this->requestAs(
            'GET',
            self::FLOWABLE . '/process_definitions?page=abc&itemsPerPage=9999&latest=maybe&unknown=x',
            [P::roleFor(P::READ)],
        );

        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        self::assertStringContainsString(NonValidatingProcessDefinitionProvider::CANNED_KEY, (string) $response->getContent());
        self::assertCount(1, $this->replacement()->calls);
        self::assertNull($this->replacement()->calls[0]['mcp_data']);
    }

    public function testWithoutApiPlatformMcpTheContainerBuildsWithoutTheDecorator(): void
    {
        $this->useKernelVariant(['api_platform_mcp' => false]);
        $container = static::getContainer();

        self::assertFalse($container->has('api_platform.mcp.handler'));
        self::assertNotContains(
            McpToolInputValidationProvider::class,
            array_column(self::chain($container->get('api_platform.state_provider.main')), 0),
        );
        self::assertSame(200, $this->requestAs('GET', self::FLOWABLE . '/process_definitions', [P::roleFor(P::READ)])->getStatusCode());
    }

    /**
     * @param array<string, mixed> $mode
     */
    private function useMode(array $mode): void
    {
        if ($mode !== []) {
            $this->useKernelVariant(self::FIXTURES + $mode);
        }
    }

    /**
     * The providers of a chain from the outside in, each with the access
     * checker's event, found by following the one inner provider each holds.
     *
     * @return list<array{class-string, ?string}>
     */
    private static function chain(object $provider): array
    {
        $chain = [];
        while ($provider instanceof ProviderInterface) {
            $inner = null;
            $event = null;
            foreach ((new \ReflectionObject($provider))->getProperties() as $property) {
                $value = $property->getValue($provider);
                if ($provider instanceof AccessCheckerProvider && $property->getName() === 'event') {
                    $event = $value;
                }
                if ($inner === null && $value instanceof ProviderInterface) {
                    $inner = $value;
                }
            }
            $chain[] = [$provider::class, $event];
            $provider = $inner;
        }

        return $chain;
    }

    private function replacement(): NonValidatingProcessDefinitionProvider
    {
        return static::getContainer()->get(NonValidatingProcessDefinitionProvider::class);
    }

    private function foreignProvider(): ForeignMcpToolProvider
    {
        return static::getContainer()->get(ForeignMcpToolProvider::class);
    }
}
