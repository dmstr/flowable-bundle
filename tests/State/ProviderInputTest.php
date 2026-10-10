<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Dmstr\Flowable\ApiResource\FlowTask;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\State\FlowJobProvider;
use Dmstr\Flowable\State\FlowTaskProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * Providers read query/list parameters from $context['mcp_data'] for an MCP
 * tool call and from the query string otherwise.
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
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')->with(self::EXPECTED_QUERY)->willReturn(self::ENVELOPE);

        $provider = new FlowTaskProvider($this->locator($client), $this->requestStack());
        $result = $provider->provide(new GetCollection(), [], self::mcpContext([
            'page' => 2,
            'itemsPerPage' => 10,
            'order' => 'asc',
            'assignee' => 'alice',
            'processInstance' => '/api/flowable/process_instances/pi-1',
            'apiConfiguration' => '0a1b2c3d',
            'notAFilter' => 'ignored',
        ]));

        self::assertInstanceOf(TraversablePaginator::class, $result);
        self::assertSame(2.0, $result->getCurrentPage());
        self::assertSame(['0a1b2c3d'], $this->requestedConfigurations);
    }

    public function testTaskListFromQueryAsBefore(): void
    {
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

        self::assertSame(['0a1b2c3d'], $this->requestedConfigurations);
    }

    public function testMcpDataIgnoresTransportQuery(): void
    {
        $transport = Request::create('/mcp', 'POST', ['assignee' => 'mallory', 'apiConfiguration' => 'ffffffff']);

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')
            ->with(['start' => 0, 'size' => 30, 'sort' => 'createTime', 'order' => 'desc'])
            ->willReturn(['data' => [], 'total' => 0, 'start' => 0, 'size' => 30]);

        (new FlowTaskProvider($this->locator($client), $this->requestStack($transport)))
            ->provide(new GetCollection(), [], self::mcpContext([]));

        self::assertSame([], $this->requestedConfigurations);
    }

    public function testTaskItemFromMcpUriVariable(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('findTask')->with('task-1')->willReturn(['id' => 'task-1']);

        $task = (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide(new Get(), ['id' => 'task-1'], self::mcpContext(['id' => 'task-1'], ['id']));

        self::assertInstanceOf(FlowTask::class, $task);
        self::assertSame('task-1', $task->id);
    }

    public function testJobKindAndBooleanFilterFromMcpData(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('listJobs');
        $client->expects($this->once())->method('listTimerJobs')
            ->with(['start' => 0, 'size' => 30, 'sort' => 'createTime', 'order' => 'desc', 'withException' => 'true'])
            ->willReturn(['data' => [], 'total' => 0, 'start' => 0, 'size' => 30]);

        (new FlowJobProvider($this->locator($client), $this->requestStack()))
            ->provide(new GetCollection(), [], self::mcpContext(['kind' => 'timer', 'withException' => true]));
    }
}
