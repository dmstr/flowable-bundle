<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:41:17 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\TaskInputSchema;
use Dmstr\Flowable\State\FlowTaskFormProvider;
use Dmstr\Flowable\State\FlowTaskProvider;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The read providers resolve `apiConfiguration` like the processors: a
 * selector string or `{uuid}` selects that configuration, any other value is
 * the locator's 400 and never the default connection.
 *
 * The contexts are built by hand, bypassing the schema check of the MCP
 * transport, so the providers' own handling is what is tested. Without
 * {@see StateTestCase::expectExplicitSelection()} the double is also the
 * default (implicit) connection: a value dropped silently would reach it.
 */
final class ProviderSelectionTest extends StateTestCase
{
    private const array EMPTY_ENVELOPE = ['data' => [], 'total' => 0, 'start' => 0, 'size' => 30];

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function acceptedSelections(): iterable
    {
        yield 'string' => [self::SELECTOR];
        yield '{uuid}' => [['uuid' => self::SELECTOR]];
    }

    #[DataProvider('acceptedSelections')]
    public function testListProviderSelectsTheConfiguration(mixed $selection): void
    {
        $this->expectExplicitSelection();
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')->willReturn(self::EMPTY_ENVELOPE);

        (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext(['apiConfiguration' => $selection]));
    }

    #[DataProvider('acceptedSelections')]
    public function testTaskFormProviderSelectsTheConfiguration(mixed $selection): void
    {
        $this->expectExplicitSelection();
        $resolver = $this->createMock(InputSchemaResolverInterface::class);
        $resolver->expects($this->once())->method('resolve')
            ->with('flow_task_complete', ['id' => 'task-1', 'apiConfiguration' => self::SELECTOR])
            ->willReturn(null);
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('findTask')->with('task-1')->willReturn(['id' => 'task-1']);

        $this->formProvider($client, $resolver)->provide(
            self::mcpTool('flowable_get_task_form'),
            ['id' => 'task-1'],
            self::mcpContext(['id' => 'task-1', 'apiConfiguration' => $selection], ['id']),
        );
    }

    /**
     * Values outside the schema: before, an array was read as "no selection"
     * and the call went to the default connection.
     *
     * @return iterable<string, array{mixed}>
     */
    public static function invalidSelections(): iterable
    {
        yield '{"uuid": 5}' => [['uuid' => 5]];
        yield '{}' => [[]];
        yield '{"uuid": ""}' => [['uuid' => '']];
        yield '{"id": <valid>}' => [['id' => self::SELECTOR]];
        yield 'list' => [[self::SELECTOR]];
        yield 'number' => [5];
        yield 'boolean' => [true];
    }

    #[DataProvider('invalidSelections')]
    public function testListProviderRejectsAnInvalidSelection(mixed $selection): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method($this->anything());

        $this->expectInvalidSelection();
        (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext(['apiConfiguration' => $selection]));
    }

    #[DataProvider('invalidSelections')]
    public function testTaskFormProviderRejectsAnInvalidSelection(mixed $selection): void
    {
        $resolver = $this->createMock(InputSchemaResolverInterface::class);
        $resolver->expects($this->never())->method('resolve');
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method($this->anything());

        $this->expectInvalidSelection();
        $this->formProvider($client, $resolver)->provide(
            self::mcpTool('flowable_get_task_form'),
            ['id' => 'task-1'],
            self::mcpContext(['id' => 'task-1', 'apiConfiguration' => $selection], ['id']),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function noSelection(): iterable
    {
        yield 'absent' => [null];
        yield 'empty string' => [''];
    }

    #[DataProvider('noSelection')]
    public function testListProviderWithoutSelectionUsesTheDefault(mixed $selection): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('listTasks')->willReturn(self::EMPTY_ENVELOPE);
        $arguments = null === $selection ? [] : ['apiConfiguration' => $selection];

        (new FlowTaskProvider($this->locator($client), $this->requestStack()))
            ->provide(self::mcpTool('flowable_list_tasks'), [], self::mcpContext($arguments));
    }

    private function expectInvalidSelection(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(FlowableClientLocator::INVALID_SELECTION);
    }

    private function formProvider(FlowableClientInterface $client, InputSchemaResolverInterface $resolver): FlowTaskFormProvider
    {
        return new FlowTaskFormProvider($this->locator($client), $this->requestStack(), new TaskInputSchema($resolver));
    }
}
