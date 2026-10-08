<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\State\DeploymentDeleteProcessor;
use Dmstr\Flowable\State\TaskCompleteProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * JSON-body and query-option processors read MCP tool arguments from
 * $context['mcp_data'] and the request otherwise.
 */
final class ProcessorInputTest extends StateTestCase
{
    private const CONFIGURATION = '0a1b2c3d';

    private const EXPECTED_PAYLOAD = [
        'action' => 'complete',
        'variables' => [
            ['name' => 'approved', 'value' => true, 'type' => 'boolean'],
            ['name' => 'comment', 'value' => 'ok', 'type' => 'string'],
        ],
    ];

    public function testTaskCompleteFromMcpDataWithoutRequest(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('completeTask')->with('task-1', self::EXPECTED_PAYLOAD);

        $context = self::mcpContext([
            'id' => 'task-1',
            'variables' => ['approved' => true, 'comment' => 'ok'],
            'apiConfiguration' => self::CONFIGURATION,
        ], ['id']);

        $result = $this->processor(TaskCompleteProcessor::class, $client)
            ->process(null, new Post(), ['id' => 'task-1'], $context);

        self::assertNull($result);
        self::assertSame([self::CONFIGURATION], $this->requestedConfigurations);
    }

    public function testTaskCompleteFromMcpDataIgnoresTransportRequest(): void
    {
        // During a tool call the request stack holds the JSON-RPC request to
        // /mcp; neither its body nor its query string are operation input.
        $transport = Request::create(
            '/mcp?apiConfiguration=ffffffff',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"jsonrpc":"2.0","method":"tools/call","id":1}',
        );

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('completeTask')->with('task-1', self::EXPECTED_PAYLOAD);

        $context = self::mcpContext([
            'id' => 'task-1',
            'variables' => ['approved' => true, 'comment' => 'ok'],
            'apiConfiguration' => ['uuid' => self::CONFIGURATION],
        ], ['id']);

        $this->processor(TaskCompleteProcessor::class, $client, $transport)
            ->process(null, new Post(), ['id' => 'task-1'], $context);

        self::assertSame([self::CONFIGURATION], $this->requestedConfigurations);
    }

    public function testTaskCompleteFromHttpRequestAsBefore(): void
    {
        $request = Request::create(
            '/api/flowable/tasks/task-1/complete?apiConfiguration='.self::CONFIGURATION,
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"variables":{"approved":true,"comment":"ok"}}',
        );

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('completeTask')->with('task-1', self::EXPECTED_PAYLOAD);

        $this->processor(TaskCompleteProcessor::class, $client, $request)
            ->process(null, new Post(), ['id' => 'task-1'], ['request' => $request]);

        self::assertSame([self::CONFIGURATION], $this->requestedConfigurations);
    }

    public function testHttpBodyApiConfigurationIsUsedWithoutQueryParameter(): void
    {
        $request = Request::create(
            '/api/flowable/tasks/task-1/complete',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"apiConfiguration":{"uuid":"'.self::CONFIGURATION.'"}}',
        );

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('completeTask')->with('task-1', ['action' => 'complete']);

        $this->processor(TaskCompleteProcessor::class, $client, $request)
            ->process(null, new Post(), ['id' => 'task-1']);

        self::assertSame([self::CONFIGURATION], $this->requestedConfigurations);
    }

    public function testMcpBodyIsValidatedAgainstTheInputSchema(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('completeTask');

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->processor(TaskCompleteProcessor::class, $client)
            ->process(null, new Post(), ['id' => 'task-1'], self::mcpContext(['id' => 'task-1', 'unknown' => 1], ['id']));
    }

    public function testMcpCallWithoutArgumentsResolvesImplicitly(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('completeTask')->with('task-1', ['action' => 'complete']);

        $this->processor(TaskCompleteProcessor::class, $client)
            ->process(null, new Post(), ['id' => 'task-1'], ['mcp_data' => [], 'uri_variables' => []]);

        self::assertSame([], $this->requestedConfigurations);
    }

    public function testDeploymentDeleteCascadeFromMcpData(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('deleteDeployment')->with('dep-1', true);

        $this->processor(DeploymentDeleteProcessor::class, $client)
            ->process(null, new Delete(), ['id' => 'dep-1'], self::mcpContext(['id' => 'dep-1', 'cascade' => true], ['id']));
    }

    public function testDeploymentDeleteCascadeFromQueryAsBefore(): void
    {
        $request = Request::create('/api/flowable/deployments/dep-1?cascade=true', 'DELETE');

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('deleteDeployment')->with('dep-1', true);

        $this->processor(DeploymentDeleteProcessor::class, $client, $request)
            ->process(null, new Delete(), ['id' => 'dep-1']);
    }

    public function testDeploymentDeleteWithoutCascade(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('deleteDeployment')->with('dep-1', false);

        $this->processor(DeploymentDeleteProcessor::class, $client)
            ->process(null, new Delete(), ['id' => 'dep-1'], self::mcpContext(['id' => 'dep-1'], ['id']));
    }
}
