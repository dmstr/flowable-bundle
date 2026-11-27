<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:28:46 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Schema;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Resource\Factory\AttributesResourceMetadataCollectionFactory;
use Dmstr\Flowable\Schema\InputSchemaLoader;
use Dmstr\Flowable\Schema\InputSchemaPath;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\AbstractFlowableProcessor;
use Dmstr\Flowable\State\DecisionExecuteProcessor;
use Dmstr\Flowable\State\EventInstanceCreateProcessor;
use Dmstr\Flowable\State\ExecutionTriggerProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobAcquireProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobBpmnErrorProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobCompleteProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobFailProcessor;
use Dmstr\Flowable\State\ExternalWorkerJobUnacquireProcessor;
use Dmstr\Flowable\State\ProcessDefinitionStartProcessor;
use Dmstr\Flowable\State\ProcessInstanceCreateProcessor;
use Dmstr\Flowable\State\TaskCompleteProcessor;
use Dmstr\Flowable\Tests\Mcp\BundleMcpTools;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * {@see InputSchemaPath::forOperation()} resolves, for every write operation
 * of the bundle, to an existing and loadable input file, and to the same file
 * the operation's processor validates against ({@see AbstractFlowableProcessor::inputSchemaPath()}).
 * Each operation is named here with its resource and verb, so the proof is
 * direct and does not go through the schema snapshot:
 *
 *  - the HTTP write operations with a body validated against a file, which
 *    the CLI mirrors build with the same (resource, verb) pair;
 *  - the HTTP write operations without such a body (uploads, deletes) are
 *    listed as such, so a new write operation fails the completeness check
 *    until it is put in one of the two lists;
 *  - the MCP write tools: their input file is `<Resource>/<verb>.input.json`
 *    and forOperation() of that pair is the same file.
 */
final class InputSchemaPathTest extends TestCase
{
    /**
     * HTTP write operations validated against an input file: resource, verb, processor.
     *
     * @var array<string, array{string, string, class-string<AbstractFlowableProcessor>}>
     */
    private const array VALIDATED_OPERATIONS = [
        'flow_decision_execute' => ['FlowDecision', 'execute', DecisionExecuteProcessor::class],
        'flow_event_instance_create' => ['FlowEventInstance', 'create', EventInstanceCreateProcessor::class],
        'flow_execution_trigger' => ['FlowExecution', 'trigger', ExecutionTriggerProcessor::class],
        'flow_external_worker_job_acquire' => ['FlowExternalWorkerJob', 'acquire', ExternalWorkerJobAcquireProcessor::class],
        'flow_external_worker_job_complete' => ['FlowExternalWorkerJob', 'complete', ExternalWorkerJobCompleteProcessor::class],
        'flow_external_worker_job_fail' => ['FlowExternalWorkerJob', 'fail', ExternalWorkerJobFailProcessor::class],
        'flow_external_worker_job_bpmnError' => ['FlowExternalWorkerJob', 'bpmnError', ExternalWorkerJobBpmnErrorProcessor::class],
        'flow_external_worker_job_unacquire' => ['FlowExternalWorkerJob', 'unacquire', ExternalWorkerJobUnacquireProcessor::class],
        'flow_process_definition_start' => ['FlowProcessDefinition', 'start', ProcessDefinitionStartProcessor::class],
        'flow_process_instance_create' => ['FlowProcessInstance', 'create', ProcessInstanceCreateProcessor::class],
        'flow_task_complete' => ['FlowTask', 'complete', TaskCompleteProcessor::class],
    ];

    /** HTTP write operations whose processor validates no body against a file */
    private const array OPERATIONS_WITHOUT_INPUT_FILE = [
        'flow_deployment_delete',
        'flow_deployment_upload',
        'flow_dmn_deployment_delete',
        'flow_dmn_deployment_upload',
        'flow_event_deployment_delete',
        'flow_event_deployment_upload',
        'flow_process_instance_delete',
    ];

    /**
     * @return iterable<string, array{string, string, class-string<AbstractFlowableProcessor>}>
     */
    public static function validatedOperations(): iterable
    {
        foreach (self::VALIDATED_OPERATIONS as $name => $row) {
            yield $name => $row;
        }
    }

    #[DataProvider('validatedOperations')]
    public function testOperationResolvesToItsProcessorsInputFile(string $resource, string $verb, string $processor): void
    {
        $path = InputSchemaPath::forOperation($resource, $verb);

        self::assertFileExists($path);
        self::assertStringEndsWith('/src/ApiResource/' . $resource . '/' . $verb . '.input.json', $path);
        self::assertSame(realpath($path), realpath((string) $processor::inputSchemaPath()), 'the processor validates against another file');
        self::assertNotNull(InputSchemaLoader::load($path), 'the input file does not load');
    }

    public function testEveryHttpWriteOperationIsListed(): void
    {
        $validated = [];
        $withoutFile = [];
        foreach (self::httpWriteOperations() as $name => [$resource, $processor]) {
            if (\is_string($processor) && is_subclass_of($processor, AbstractFlowableProcessor::class) && $processor::INPUT_SCHEMA !== null) {
                $validated[$name] = [$resource, $processor];
            } else {
                $withoutFile[] = $name;
            }
        }
        ksort($validated);
        sort($withoutFile);

        $expected = array_map(static fn (array $row): array => [$row[0], $row[2]], self::VALIDATED_OPERATIONS);
        ksort($expected);
        $expectedWithoutFile = self::OPERATIONS_WITHOUT_INPUT_FILE;
        sort($expectedWithoutFile);

        self::assertSame($expected, $validated);
        self::assertSame($expectedWithoutFile, $withoutFile);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function mcpWriteTools(): iterable
    {
        foreach (array_keys(BundleMcpTools::writeTools()) as $name) {
            yield $name => [$name];
        }
    }

    #[DataProvider('mcpWriteTools')]
    public function testMcpWriteToolInputFileIsTheFileOfItsResourceAndVerb(string $name): void
    {
        $tool = BundleMcpTools::writeTools()[$name];
        self::assertInstanceOf(McpTool::class, $tool);

        $input = $tool->getExtraProperties()[McpToolInputSchemaFactory::EXTRA_KEY]['input'] ?? null;
        self::assertIsString($input, 'the write tool declares no input file');
        self::assertSame(1, preg_match('#^([A-Za-z]+)/([A-Za-z]+)\.input\.json$#', $input, $match), $input);

        $path = InputSchemaPath::forOperation($match[1], $match[2]);
        self::assertFileExists($path);
        self::assertSame(InputSchemaPath::relative($input), $path);
        self::assertNotNull(InputSchemaLoader::load($path), 'the input file does not load');
    }

    public function testRelativeIgnoresLeadingSlashes(): void
    {
        self::assertSame(
            InputSchemaPath::forOperation('FlowTask', 'complete'),
            InputSchemaPath::relative('//FlowTask/complete.input.json'),
        );
    }

    /**
     * The HTTP operations with a write method, from the attribute metadata
     * of src/ApiResource: name => [resource short name, processor].
     *
     * @return array<string, array{?string, mixed}>
     */
    private static function httpWriteOperations(): array
    {
        $factory = new AttributesResourceMetadataCollectionFactory();
        $operations = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/ApiResource/*.php') ?: [] as $file) {
            $class = 'Dmstr\\Flowable\\ApiResource\\' . basename($file, '.php');
            foreach ($factory->create($class) as $resource) {
                foreach ($resource->getOperations() ?? [] as $name => $operation) {
                    if ($operation instanceof HttpOperation
                        && \in_array($operation->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                        $operations[(string) ($operation->getName() ?? $name)] = [$resource->getShortName(), $operation->getProcessor()];
                    }
                }
            }
        }

        return $operations;
    }
}
