<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:09:45 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Fixtures\ForeignMcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;

/**
 * Provider of {@see ForeignMcpTool}: validates nothing, records the MCP
 * arguments it receives and echoes them in `received`.
 *
 * @implements ProviderInterface<ForeignMcpTool>
 */
final class ForeignMcpToolProvider implements ProviderInterface
{
    /** @var list<mixed> the mcp_data of every call */
    public array $calls = [];

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->calls[] = $context['mcp_data'] ?? null;

        $result = new ForeignMcpTool();
        $result->received = json_encode($context['mcp_data'] ?? null, \JSON_THROW_ON_ERROR);

        return $result;
    }
}
