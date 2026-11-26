<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:01:03 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Dmstr\Flowable\Tests\Http\ApiTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * The MCP switches must be literal booleans when the real kernel's container
 * is built (LiteralMcpSwitchesPass): from the `dmstr_flowable` configuration
 * and from an application override of the parameters. The full value matrix
 * is covered by {@see \Dmstr\Flowable\Tests\DependencyInjection\LiteralMcpSwitchesPassTest};
 * every case here compiles a container, so there are few of them.
 *
 * A failed build writes no container, and every option set has its own
 * environment and cache directory ({@see TestKernel}), so a failed boot
 * cannot leak into another test; the read cases run twice with the same
 * options and so prove that a failed build is not reused either.
 */
final class McpSwitchBuildTest extends ApiTestCase
{
    private const string ADMIN = 'ROLE_FLOWABLE_ADMIN';

    private const string READ_TOOL = 'flowable_list_process_definitions';

    private const string WRITE_TOOL = 'flowable_start_process';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function placeholderSwitches(): iterable
    {
        yield 'read, env 1' => ['read', '1'];
        yield 'read, env 0' => ['read', '0'];
        yield 'write' => ['write', '1'];
        yield 'deploy' => ['deploy', '1'];
    }

    #[DataProvider('placeholderSwitches')]
    public function testEnvPlaceholderInTheConfigurationFailsTheBuild(string $switch, string $envValue): void
    {
        $env = 'FLOWABLE_MCP_' . strtoupper($switch);
        $mcp = ['read' => true, 'write' => true, 'deploy' => true];
        $mcp[$switch] = '%env(bool:' . $env . ')%';

        $this->withEnv($env, $envValue, function () use ($switch, $mcp): void {
            $this->assertBuildFails(['mcp' => $mcp], 'dmstr_flowable.mcp.' . $switch);
        });
    }

    public function testNonBooleanParameterOverrideFailsTheBuild(): void
    {
        $this->withEnv('FLOWABLE_MCP_READ', '1', function (): void {
            $this->assertBuildFails(['parameters' => ['dmstr_flowable.mcp.read' => '%env(bool:FLOWABLE_MCP_READ)%']], 'dmstr_flowable.mcp.read');
        });
        $this->assertBuildFails(['parameters' => ['dmstr_flowable.mcp.read' => 'true']], 'dmstr_flowable.mcp.read');
    }

    public function testBooleanParameterOverrideWinsOverTheConfiguration(): void
    {
        $this->useKernelVariant([
            'mcp' => ['read' => true, 'write' => true, 'deploy' => true],
            'parameters' => ['dmstr_flowable.mcp.read' => false],
        ]);
        $tools = $this->mcpToolNames([self::ADMIN]);
        self::assertNotContains(self::READ_TOOL, $tools);
        self::assertContains(self::WRITE_TOOL, $tools);

        $this->useKernelVariant([
            'mcp' => ['read' => false, 'write' => true, 'deploy' => true],
            'parameters' => ['dmstr_flowable.mcp.read' => true],
        ]);
        self::assertContains(self::READ_TOOL, $this->mcpToolNames([self::ADMIN]));
    }

    /**
     * @param array<string, mixed> $kernelOptions
     */
    private function assertBuildFails(array $kernelOptions, string $parameter): void
    {
        self::ensureKernelShutdown();
        $kernel = new TestKernel(TestKernel::ENV_WITH_VOTER, true, $kernelOptions);
        try {
            $kernel->boot();
            self::fail('The container was built with ' . json_encode($kernelOptions));
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('"' . $parameter . '" must be a literal boolean', $e->getMessage());
        } finally {
            $kernel->shutdown();
        }
    }

    private function withEnv(string $name, string $value, callable $test): void
    {
        $previous = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv($name . '=' . $value);
        $_ENV[$name] = $_SERVER[$name] = $value;
        try {
            $test();
        } finally {
            putenv($previous[0] === false ? $name : $name . '=' . $previous[0]);
            if ($previous[1] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $previous[1];
            }
            if ($previous[2] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $previous[2];
            }
        }
    }
}
