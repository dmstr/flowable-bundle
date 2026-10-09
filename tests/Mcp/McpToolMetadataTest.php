<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Mcp;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 14 MCP tools, read from the real attribute metadata of src/ApiResource.
 */
final class McpToolMetadataTest extends TestCase
{
    /** No MCP tool is open to ROLE_USER, read tools included. */
    private const ADMIN = "is_granted('ROLE_FLOWABLE_ADMIN')";

    /**
     * name => destructiveHint; every state-changing tool that cannot be
     * undone is destructive, so clients ask before calling it.
     *
     * @var array<string, bool>
     */
    private const DESTRUCTIVE = [
        'flowable_list_process_definitions' => false,
        'flowable_list_tasks' => false,
        'flowable_get_task_form' => false,
        'flowable_get_process_status' => false,
        'flowable_history_get' => false,
        'flowable_system_list_deadletter_jobs' => false,
        'flowable_start_process' => true,
        'flowable_complete_task' => true,
        'flowable_dmn_evaluate' => false,
        'flowable_events_send' => true,
        'flowable_system_trigger_execution' => true,
        'flowable_dmn_deploy' => true,
        'flowable_deploy_bundle' => true,
        'flowable_system_execute_timer_job' => true,
    ];

    /** Tools that need the mcp.deploy switch on top of mcp.write. */
    private const DEPLOY_TOOLS = ['flowable_dmn_deploy', 'flowable_deploy_bundle'];

    /**
     * name => [resource short name, security, readOnlyHint, tag, input file, has id argument]
     *
     * @var array<string, array{string, string, bool, string, string, bool}>
     */
    private const TOOLS = [
        'flowable_list_process_definitions' => ['FlowProcessDefinition', self::ADMIN, true, 'Flowable', 'FlowProcessDefinition/mcpList.input.json', false],
        'flowable_list_tasks' => ['FlowTask', self::ADMIN, true, 'Flowable', 'FlowTask/mcpList.input.json', false],
        'flowable_get_task_form' => ['FlowTask', self::ADMIN, true, 'Flowable', 'FlowTask/mcpForm.input.json', true],
        'flowable_get_process_status' => ['FlowProcessInstance', self::ADMIN, true, 'Flowable', 'FlowProcessInstance/mcpStatus.input.json', true],
        'flowable_history_get' => ['FlowHistoricProcessInstance', self::ADMIN, true, 'Flowable/History', 'FlowHistoricProcessInstance/mcpHistory.input.json', true],
        'flowable_system_list_deadletter_jobs' => ['FlowJob', self::ADMIN, true, 'Flowable/System', 'FlowJob/mcpDeadletterList.input.json', false],
        'flowable_start_process' => ['FlowProcessInstance', self::ADMIN, false, 'Flowable', 'FlowProcessInstance/create.input.json', false],
        'flowable_complete_task' => ['FlowTask', self::ADMIN, false, 'Flowable', 'FlowTask/complete.input.json', true],
        'flowable_dmn_evaluate' => ['FlowDecision', self::ADMIN, false, 'Flowable/DMN', 'FlowDecision/execute.input.json', false],
        'flowable_events_send' => ['FlowEventInstance', self::ADMIN, false, 'Flowable/Events', 'FlowEventInstance/create.input.json', false],
        'flowable_system_trigger_execution' => ['FlowExecution', self::ADMIN, false, 'Flowable/System', 'FlowExecution/trigger.input.json', true],
        'flowable_dmn_deploy' => ['FlowDmnDeployment', self::ADMIN, false, 'Flowable/DMN', 'FlowDmnDeployment/mcpDeploy.input.json', false],
        'flowable_deploy_bundle' => ['FlowDeployment', self::ADMIN, false, 'Flowable', 'FlowDeployment/mcpBundle.input.json', false],
        'flowable_system_execute_timer_job' => ['FlowJob', self::ADMIN, false, 'Flowable/System', 'FlowJob/mcpExecuteTimer.input.json', true],
    ];

    private const READ_TOOLS = [
        'flowable_list_process_definitions',
        'flowable_list_tasks',
        'flowable_get_task_form',
        'flowable_get_process_status',
        'flowable_history_get',
        'flowable_system_list_deadletter_jobs',
    ];

    public function testEveryToolExistsWithItsContract(): void
    {
        $tools = self::tools(new AttributesResourceMetadataCollectionFactory());

        self::assertEqualsCanonicalizing(array_keys(self::TOOLS), array_keys($tools));

        foreach (self::TOOLS as $name => [$shortName, $security, $readOnly, $tag, $input, $hasId]) {
            $tool = $tools[$name];
            self::assertSame($name, $tool->getName());
            self::assertSame('Dmstr\\Flowable\\ApiResource\\'.$shortName, $tool->getClass(), $name);
            self::assertSame($security, $tool->getSecurity(), $name);
            self::assertSame($readOnly, McpToolSwitchResourceMetadataCollectionFactory::isReadOnly($tool), $name);
            self::assertSame(['de.dmstr/tag' => $tag], $tool->getMeta(), $name);

            $annotations = $tool->getAnnotations();
            self::assertIsArray($annotations, $name);
            foreach (['readOnlyHint', 'destructiveHint', 'idempotentHint'] as $hint) {
                self::assertIsBool($annotations[$hint] ?? null, $name.' '.$hint);
            }
            self::assertSame(self::DESTRUCTIVE[$name], $annotations['destructiveHint'], $name.' destructiveHint');
            self::assertSame(
                \in_array($name, self::DEPLOY_TOOLS, true) ? 'deploy' : null,
                $tool->getExtraProperties()[McpToolSwitchResourceMetadataCollectionFactory::SWITCH_KEY] ?? null,
                $name,
            );

            $description = (string) $tool->getDescription();
            self::assertNotSame('', $description, $name);
            self::assertStringNotContainsString("\n", $description, $name);
            self::assertStringContainsString('Returns', $description, $name);

            // Read tools are read through a provider; write tools skip the
            // read step and run their processor.
            if ($readOnly) {
                self::assertNotNull($tool->getProvider(), $name);
                self::assertNull($tool->getProcessor(), $name);
            } else {
                self::assertFalse($tool->canRead(), $name);
                self::assertNotNull($tool->getProcessor(), $name);
            }

            self::assertSame($input, $tool->getExtraProperties()[McpToolInputSchemaFactory::EXTRA_KEY]['input'] ?? null, $name);
            self::assertSame($hasId ? ['id'] : [], array_keys($tool->getUriVariables() ?? []), $name);
        }

        self::assertInstanceOf(McpToolCollection::class, $tools['flowable_list_tasks']);
        self::assertInstanceOf(McpToolCollection::class, $tools['flowable_list_process_definitions']);
        self::assertInstanceOf(McpToolCollection::class, $tools['flowable_system_list_deadletter_jobs']);
        self::assertSame('deadletter', $tools['flowable_system_list_deadletter_jobs']->getExtraProperties()['dmstr_flowable_job_kind'] ?? null);
    }

    /**
     * @return iterable<string, array{bool, bool, bool, list<string>}>
     */
    public static function switches(): iterable
    {
        $write = array_values(array_diff(array_keys(self::TOOLS), self::READ_TOOLS, self::DEPLOY_TOOLS));

        yield 'all' => [true, true, true, array_keys(self::TOOLS)];
        yield 'read and write' => [true, true, false, [...self::READ_TOOLS, ...$write]];
        yield 'read only' => [true, false, false, self::READ_TOOLS];
        yield 'write only' => [false, true, false, $write];
        yield 'write and deploy' => [false, true, true, [...$write, ...self::DEPLOY_TOOLS]];
        yield 'deploy without write' => [false, false, true, []];
        yield 'neither' => [false, false, false, []];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('switches')]
    public function testSwitchesKeepTheExpectedTools(bool $read, bool $write, bool $deploy, array $expected): void
    {
        $factory = new McpToolSwitchResourceMetadataCollectionFactory(new AttributesResourceMetadataCollectionFactory(), $read, $write, $deploy);

        self::assertEqualsCanonicalizing($expected, array_keys(self::tools($factory)));
    }

    public function testInputSchemasComeFromTheInputFiles(): void
    {
        $tools = self::tools(new AttributesResourceMetadataCollectionFactory());

        foreach (self::TOOLS as $name => [, , , , $input, $hasId]) {
            $schema = McpToolInputSchemaFactory::inputSchema($tools[$name]);
            self::assertIsArray($schema, $name);
            self::assertSame('object', $schema['type'] ?? null, $name);
            self::assertFalse($schema['additionalProperties'] ?? null, $name);
            foreach (['$schema', '$id', 'title', 'allOf', 'anyOf', 'oneOf'] as $dropped) {
                self::assertArrayNotHasKey($dropped, $schema, $name);
            }

            $file = json_decode((string) file_get_contents(McpToolInputSchemaFactory::path($input)), true);
            self::assertIsArray($file, $input);
            $expectedProperties = array_keys($file['properties']);
            if ($hasId) {
                array_unshift($expectedProperties, 'id');
                self::assertContains('id', $schema['required'] ?? [], $name);
                self::assertSame('string', $schema['properties']['id']['type'], $name);
            }
            self::assertSame($expectedProperties, array_keys($schema['properties']), $name);
        }
    }

    public function testEmptySchemaObjectsSurviveAsObjects(): void
    {
        $tools = self::tools(new AttributesResourceMetadataCollectionFactory());
        $schema = McpToolInputSchemaFactory::inputSchema($tools['flowable_complete_task']);

        // {"value": {}} in the variables list must stay an object, not [].
        self::assertSame(
            '{}',
            json_encode($schema['properties']['variables']['oneOf'][1]['items']['properties']['value'] ?? null),
        );
    }

    public function testSchemaFactoryServesToolsAndPassesOthersThrough(): void
    {
        $tools = self::tools(new AttributesResourceMetadataCollectionFactory());
        $inner = $this->createMock(SchemaFactoryInterface::class);
        $inner->expects($this->once())->method('buildSchema')->willReturn(new Schema());
        $factory = new McpToolInputSchemaFactory($inner);

        $schema = $factory->buildSchema('X', 'json', Schema::TYPE_INPUT, $tools['flowable_complete_task']);
        self::assertSame(['id', 'variables', 'apiConfiguration'], array_keys($schema['properties']));
        self::assertArrayNotHasKey('$schema', $schema->getArrayCopy());

        // Output schemas are not touched.
        $factory->buildSchema('X', 'json', Schema::TYPE_OUTPUT, $tools['flowable_complete_task']);
    }

    /**
     * All McpTool entries of the bundle's resources, by name.
     *
     * @return array<string, McpTool>
     */
    private static function tools(ResourceMetadataCollectionFactoryInterface $factory): array
    {
        $tools = [];
        foreach (glob(\dirname(__DIR__, 2).'/src/ApiResource/*.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\ApiResource\\'.basename($file, '.php');
            foreach ($factory->create($class) as $resource) {
                foreach ($resource->getMcp() ?? [] as $tool) {
                    if ($tool instanceof McpTool) {
                        self::assertArrayNotHasKey((string) $tool->getName(), $tools, 'duplicate tool name');
                        $tools[(string) $tool->getName()] = $tool;
                    }
                }
            }
        }

        return $tools;
    }
}
// - revised 2026-10-09 (admin-only tools, destructiveHint, mcp.deploy)
