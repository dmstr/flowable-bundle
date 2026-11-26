<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:30:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Schema;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Schema\InputSchemaLoader;
use Dmstr\Flowable\Schema\StartFormInputSchemaResolver;
use Dmstr\Flowable\Schema\TaskFormInputSchemaResolver;
use Dmstr\Flowable\Schema\TaskFormSchemaSourceInterface;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\Tests\Kernel\TestKernel;
use Dmstr\Flowable\Tests\State\StateTestCase;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Dmstr\OpenApiJsonSchema\OpenApi\OperationInputSchemaDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Pins every input schema the bundle delivers to the state of release 0.12.1,
 * with the intended changes since.
 *
 * The fixtures under snapshot-0.12.1/ were captured from the 0.12.1 code,
 * before the `apiConfiguration` block was moved into the shared definition
 * (src/Schema/api-configuration.json). The intended changes since:
 *  - the `{uuid}` object branch of `apiConfiguration` carries the same
 *    selector `pattern` as the string branch;
 *  - the upload schemas FlowDmnDeployment/mcpDeploy and
 *    FlowDeployment/mcpBundle carry the size limits: `maxLength` 255 on the
 *    file name and on deploymentName, deploymentSource, category and
 *    tenantId, `maxLength` 1400000 on the inline content (a pre-filter, the
 *    byte limit is enforced by State\UploadRule), `maxItems` 20 on the bundle
 *    files, and the descriptions name the limits;
 *  - the `name` pattern of FlowDmnDeployment/mcpDeploy accepts `.dmn` only
 *    (archives stay with the HTTP upload), its descriptions say so.
 * Each test rebuilds one delivery path
 * and compares its JSON encoding byte for byte, so key order and the {} / []
 * distinction are part of the contract:
 *
 *  - mcp.json: the inputSchema of every MCP tool
 *    ({@see McpToolInputSchemaFactory::inputSchema()});
 *  - resolver.json: every *.input.json file through the input-schema resolver
 *    chain without context, which is what the OpenAPI decorator injects and
 *    what the form-schema endpoints fall back to for flow_task_complete and
 *    flow_process_definition_start;
 *  - openapi.json: the requestBody / query parameters the OpenAPI decorator
 *    of dmstr/openapi-json-schema-bundle builds from that chain;
 *  - validator.json: the schema object the input validator checks against;
 *  - form.json: the envelopes the two PHP form-schema resolvers build.
 *
 * None of these outputs may carry a `$ref` or `$defs`: a client gets the
 * resolved schema only. To re-capture after an intended schema change,
 * write the encoded outputs over the fixtures and review the diff.
 */
final class InputSchemaSnapshotTest extends StateTestCase
{
    private const string FIXTURES = __DIR__.'/snapshot-0.12.1';

    private const int JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

    /** The form field schema the stub form source returns. */
    private const array FIELDS = [
        'type' => 'object',
        'properties' => ['approved' => ['type' => 'boolean', 'title' => 'Approved']],
        'required' => ['approved'],
    ];

    private static ?TestKernel $kernel = null;

    public static function tearDownAfterClass(): void
    {
        self::$kernel?->shutdown();
        self::$kernel = null;
    }

    public function testMcpToolInputSchemas(): void
    {
        $schemas = [];
        foreach (self::mcpTools() as $name => $tool) {
            $schemas[$name] = McpToolInputSchemaFactory::inputSchema($tool);
        }
        self::assertCount(14, $schemas);

        $this->assertSnapshot('mcp.json', $schemas);
    }

    public function testResolverChainSchemas(): void
    {
        $this->assertSnapshot('resolver.json', self::resolvedByChain());
    }

    public function testOpenApiRequestSchemas(): void
    {
        $this->assertSnapshot('openapi.json', self::openApiInputs());
    }

    public function testValidatorSchemas(): void
    {
        $schemas = [];
        foreach (self::inputFiles() as $input) {
            $schemas[$input] = InputSchemaLoader::load(McpToolInputSchemaFactory::path($input));
        }

        $this->assertSnapshot('validator.json', $schemas);
    }

    public function testPhpBuiltFormSchemas(): void
    {
        $this->assertSnapshot('form.json', $this->formSchemas());
    }

    /**
     * The validator checks against the resolved schema: an unresolved `$ref`
     * would make opis/json-schema fail to load the reference instead.
     */
    public function testValidatorChecksTheSharedDefinition(): void
    {
        $validator = new InputSchemaValidator();
        $path = McpToolInputSchemaFactory::path('FlowTask/complete.input.json');

        $validator->validate(['apiConfiguration' => '0a1b2c3d'], $path);
        $validator->validate(['apiConfiguration' => ['uuid' => '0a1b2c3d']], $path);

        $this->expectException(UnprocessableEntityHttpException::class);
        $validator->validate(['apiConfiguration' => 'not-a-selector'], $path);
    }

    public function testEveryInputFileIsCovered(): void
    {
        self::assertCount(21, self::inputFiles());
        self::assertSame(self::inputFiles(), array_keys(self::resolvedByChain()));
    }

    /**
     * All *.input.json files, relative to src/ApiResource, sorted.
     *
     * @return list<string>
     */
    private static function inputFiles(): array
    {
        $root = McpToolInputSchemaFactory::path('');
        $files = [];
        foreach (glob($root.'*/*.input.json') ?: [] as $path) {
            $files[] = substr($path, \strlen($root));
        }
        sort($files);

        return $files;
    }

    /** `FlowTask/mcpList.input.json` → `flow_task_mcpList`, the name the file resolver looks up. */
    private static function operationName(string $input): string
    {
        [$entity, $file] = explode('/', $input);

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $entity)).'_'.substr($file, 0, -\strlen('.input.json'));
    }

    /** @return array<string, array<string,mixed>|null> */
    private static function resolvedByChain(): array
    {
        $chain = self::container()->get(InputSchemaResolverInterface::class);
        \assert($chain instanceof InputSchemaResolverInterface);

        $schemas = [];
        foreach (self::inputFiles() as $input) {
            $schemas[$input] = $chain->resolve(self::operationName($input));
        }

        return $schemas;
    }

    /**
     * The request input of every operation the OpenAPI decorator patched,
     * keyed by "<METHOD> <path>".
     *
     * @return array<string, array<string,mixed>>
     */
    private static function openApiInputs(): array
    {
        $container = self::container();
        $factory = $container->get('api_platform.openapi.factory');
        $chain = $container->get(InputSchemaResolverInterface::class);
        \assert($factory instanceof \ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface && $chain instanceof InputSchemaResolverInterface);

        $openApi = (new OperationInputSchemaDecorator($factory, $chain))();

        $inputs = [];
        foreach ($openApi->getPaths()->getPaths() as $path => $pathItem) {
            foreach (['Post', 'Put', 'Patch', 'Get', 'Delete'] as $verb) {
                $operation = $pathItem->{'get'.$verb}();
                if (!$operation instanceof Operation || null === $chain->resolve((string) $operation->getOperationId())) {
                    continue;
                }
                $requestBody = $operation->getRequestBody();
                $inputs[strtoupper($verb).' '.$path] = [
                    'operationId' => $operation->getOperationId(),
                    'requestBody' => null === $requestBody ? null : [
                        'description' => $requestBody->getDescription(),
                        'content' => $requestBody->getContent()?->getArrayCopy(),
                        'required' => $requestBody->getRequired(),
                    ],
                    'parameters' => array_map(
                        static fn (Parameter $parameter): array => [
                            'name' => $parameter->getName(),
                            'in' => $parameter->getIn(),
                            'description' => $parameter->getDescription(),
                            'required' => $parameter->getRequired(),
                            'schema' => $parameter->getSchema(),
                        ],
                        $operation->getParameters() ?? [],
                    ),
                ];
            }
        }
        ksort($inputs);

        return $inputs;
    }

    /** @return array<string, array<string,mixed>|null> */
    private function formSchemas(): array
    {
        $client = $this->createStub(FlowableClientInterface::class);
        $client->method('getTaskFormData')->willReturn(['formKey' => 'review']);
        $client->method('getStartFormData')->willReturn(['formKey' => 'request']);
        $client->method('findTask')->willReturn(['name' => 'Review']);
        $client->method('findProcessDefinition')->willReturn(['name' => 'Request', 'key' => 'request']);

        $source = new class (self::FIELDS) implements TaskFormSchemaSourceInterface {
            /** @param array<string,mixed> $fields */
            public function __construct(private readonly array $fields)
            {
            }

            public function build(array $formData, FlowableClientInterface $client): ?array
            {
                return $this->fields;
            }
        };
        $locator = $this->locator($client);

        return [
            'flow_task_complete' => (new TaskFormInputSchemaResolver($locator, [$source]))
                ->resolve('flow_task_complete', ['id' => 'task-1']),
            'flow_process_definition_start' => (new StartFormInputSchemaResolver($locator, [$source]))
                ->resolve('flow_process_definition_start', ['id' => 'definition-1']),
        ];
    }

    private static function container(): ContainerInterface
    {
        if (null === self::$kernel) {
            self::$kernel = new TestKernel(TestKernel::ENV_WITH_VOTER, false);
            self::$kernel->boot();
        }
        $container = self::$kernel->getContainer()->get('test.service_container');
        \assert($container instanceof ContainerInterface);

        return $container;
    }

    /**
     * Compares the JSON encoding of a delivered output with its fixture, and
     * makes sure it carries no reference: a client gets resolved schemas only.
     */
    private function assertSnapshot(string $fixture, mixed $actual): void
    {
        $encoded = json_encode($actual, self::JSON_FLAGS)."\n";

        self::assertStringNotContainsString('"$ref"', $encoded, $fixture);
        self::assertStringNotContainsString('"$defs"', $encoded, $fixture);
        self::assertStringEqualsFile(self::FIXTURES.'/'.$fixture, $encoded, $fixture);
    }
}
// - revised 2026-11-26 (fixtures carry the selector pattern in the {uuid} branch)
// - revised 2026-11-26 (upload limits and .dmn-only pattern in the fixtures, tools from StateTestCase)
