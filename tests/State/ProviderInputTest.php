<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Dmstr\Flowable\ApiResource\FlowTask;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\FlowJobProvider;
use Dmstr\Flowable\State\FlowProcessDefinitionProvider;
use Dmstr\Flowable\State\FlowTaskProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Providers read query/list parameters from $context['mcp_data'] for an MCP
 * tool call and from the query string otherwise.
 *
 * An MCP call goes through a real McpTool: the provider validates the
 * arguments against the tool's input schema first, so only arguments the
 * schema declares reach it.
 */
final class ProviderInputTest extends StateTestCase
{
    private const EXPECTED_QUERY = [
        'processInstanceId' => 'pi-1',
        'start' => 10,
        'size' => 10,
        'sort' => 'createTime',
        'order' => 'asc',
        'assignee' => 'alice',
    ];

    private const ENVELOPE = [
        'data' => [['id' => 'task-1', 'name' => 'Review']],
        'total' => 11,
        'start' => 10,
        'size' => 10,
    ];

    public function testTaskListFromMcpData(): void
    {
        $this->expectExplicitSelection();

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')->with(self::EXPECTED_QUERY)->willReturn(self::ENVELOPE);

        $provider = new FlowTaskProvider($this->locator($client), $this->requestStack());
        $result = $provider->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext([
            'page' => 2,
            'itemsPerPage' => 10,
            'order' => 'asc',
            'assignee' => 'alice',
            'processInstanceId' => 'pi-1',
            'apiConfiguration' => '0a1b2c3d',
        ]));

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(2.0, $result->getCurrentPage());
    }

    public function testTaskListRejectsAnArgumentOutsideItsSchema(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method($this->anything());

        $this->expectException(UnprocessableEntityHttpException::class);
        (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext(['notAFilter' => 'ignored']));
    }

    public function testTaskListFromQueryAsBefore(): void
    {
        $this->expectExplicitSelection();

        $request = Request::create('/api/flowable/tasks', 'GET', [
            'page' => '2',
            'itemsPerPage' => '10',
            'order' => 'asc',
            'assignee' => 'alice',
            'processInstance' => '/api/flowable/process_instances/pi-1',
            'apiConfiguration' => '0a1b2c3d',
        ]);

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')->with(self::EXPECTED_QUERY)->willReturn(self::ENVELOPE);

        (new FlowTaskProvider($this->locator($client), $this->requestStack($request)))
            ->provide(new GetCollection(), [], ['request' => $request]);
    }

    public function testMcpDataIgnoresTransportQuery(): void
    {
        $transport = Request::create('/mcp', 'POST', ['assignee' => 'mallory', 'apiConfiguration' => 'ffffffff']);

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')
            ->with(['start' => 0, 'size' => 30, 'sort' => 'createTime', 'order' => 'desc'])
            ->willReturn(['data' => [], 'total' => 0, 'start' => 0, 'size' => 30]);

        (new FlowTaskProvider($this->locator($client), $this->requestStack($transport)))
            ->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext([]));
    }

    public function testTaskItemFromMcpUriVariable(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('findTask')->with('task-1')->willReturn(['id' => 'task-1']);

        // No bundle tool reads a single task; a tool on the item operation
        // with an input schema that only knows the id stands in for one.
        $tool = new McpTool(
            uriVariables: ['id' => new Link(identifiers: ['id'])],
            extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => ['input' => 'FlowTask/mcpForm.input.json']],
        );
        $task = (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide($tool, ['id' => 'task-1'], self::mcpContext(['id' => 'task-1'], ['id']));

        self::assertInstanceOf(FlowTask::class, $task);
        self::assertSame('task-1', $task->id);
    }

    public function testBooleanFilterFromMcpData(): void
    {
        // A boolean tool argument reaches Flowable spelled "true"
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listProcessDefinitions')
            ->with(['start' => 0, 'size' => 30, 'latest' => 'true'])
            ->willReturn(['data' => [], 'total' => 0, 'start' => 0, 'size' => 30]);

        (new FlowProcessDefinitionProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_list_process_definitions'), [], self::mcpContext(['latest' => true]));
    }

    public function testJobKindAndBooleanFilterFromQuery(): void
    {
        // No MCP tool takes a kind (the deadletter tool pins it), so the kind
        // is read from the query string only.
        $request = Request::create('/api/flowable/jobs', 'GET', ['kind' => 'timer', 'withException' => 'true']);
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('listJobs');
        $client->expects($this->once())->method('listTimerJobs')
            ->with(['start' => 0, 'size' => 30, 'sort' => 'createTime', 'order' => 'desc', 'withException' => 'true'])
            ->willReturn(['data' => [], 'total' => 0, 'start' => 0, 'size' => 30]);

        (new FlowJobProvider($this->locator($client), $this->requestStack($request)))
            ->provide(new GetCollection(), [], ['request' => $request]);
    }
}
// - revised 2026-11-26 (explicit selection proven through a decoy configuration)
// - revised 2026-11-26 (MCP calls through real McpTool operations, arguments validated against the tool schema)
