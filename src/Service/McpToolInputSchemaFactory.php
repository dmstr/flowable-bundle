<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use ApiPlatform\JsonSchema\Schema;
use ApiPlatform\JsonSchema\SchemaFactoryInterface;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Operation;
use Dmstr\Flowable\Metadata\McpToolSwitchResourceMetadataCollectionFactory;
use Dmstr\Flowable\Schema\InputSchemaLoader;
use Dmstr\Flowable\Schema\InputSchemaPath;

/**
 * Serves the input schema of this bundle's MCP tools from the operation input
 * files next to the ApiResource classes (`<Resource>/<verb>.input.json`).
 *
 * API Platform's MCP loader derives a tool's inputSchema from the input class
 * of the McpTool (here: the pass-through resource), which would advertise the
 * resource's output fields as tool arguments. The bundle's tools are fed from
 * the raw tool arguments instead (see State\OperationInputTrait), and their
 * contract is the JSON schema of the input file. A tool opts in with
 *
 *     extraProperties: [McpToolInputSchemaFactory::EXTRA_KEY => [
 *         'input' => 'FlowTask/complete.input.json',
 *         'uriVariables' => ['id' => 'Id of the user task.'],
 *     ]]
 *
 * The advertised schema is the file's schema, its shared definitions
 * resolved by {@see InputSchemaLoader} (no `$ref`, no `$defs`), with
 *  - every URI variable of the tool (e.g. "id") added as a required string
 *    property, described by the 'uriVariables' entry — the MCP handler copies
 *    these arguments into $uriVariables, and they are stripped again before
 *    the body is validated;
 *  - `$schema`, `$id` and `title` removed, and top-level `allOf`/`anyOf`/
 *    `oneOf` removed: they only express "one of these keys is required",
 *    which several MCP clients cannot represent at the root. The tool
 *    description names the rule.
 *
 * This class only publishes the schema; it enforces nothing. Enforcement is
 * done by the tools' state classes, in one of two ways:
 *  - the write processors validate the tool arguments minus the URI variables
 *    against the full input file (Service\InputSchemaValidator), so a dropped
 *    root `allOf`/`anyOf`/`oneOf` is still checked;
 *  - the read tools' providers and flowable_dmn_deploy validate the tool
 *    arguments against exactly this published schema, URI variables included
 *    (State\McpToolInputValidator), and reject the call when it cannot be
 *    built.
 *
 * Decorates api_platform.mcp.json_schema.schema_factory (registered only when
 * API Platform's MCP support is enabled). It acts only on this bundle's
 * resources (the namespace guard of the tool switches,
 * {@see McpToolSwitchResourceMetadataCollectionFactory::isBundleResource()});
 * all other schemas, including those of another bundle's or the
 * application's tools that happen to carry EXTRA_KEY, pass through. An input
 * path containing `..` is rejected, so no file outside src/ApiResource is
 * read. A tool whose schema cannot be built is removed from the metadata by
 * the tool switches and is therefore never asked for here.
 */
final class McpToolInputSchemaFactory implements SchemaFactoryInterface
{
    public const EXTRA_KEY = 'dmstr_flowable_mcp';

    private const DROPPED_ROOT_KEYS = ['$schema', '$id', 'title', 'allOf', 'anyOf', 'oneOf'];

    public function __construct(
        private readonly SchemaFactoryInterface $decorated,
    ) {
    }

    public function buildSchema(string $className, string $format = 'json', string $type = Schema::TYPE_OUTPUT, ?Operation $operation = null, ?Schema $schema = null, ?array $serializerContext = null, bool $forceCollection = false): Schema
    {
        $input = Schema::TYPE_INPUT === $type && $operation instanceof McpTool
            && McpToolSwitchResourceMetadataCollectionFactory::isBundleResource($className)
            ? self::inputSchema($operation) : null;
        if (null === $input) {
            return $this->decorated->buildSchema($className, $format, $type, $operation, $schema, $serializerContext, $forceCollection);
        }

        $result = new Schema(Schema::VERSION_JSON_SCHEMA);
        unset($result['$schema']);
        foreach ($input as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * The advertised input schema of a tool, or null when the tool does not
     * declare an input file.
     *
     * @return array<string,mixed>|null
     */
    public static function inputSchema(McpTool $tool): ?array
    {
        $spec = $tool->getExtraProperties()[self::EXTRA_KEY] ?? null;
        if (!\is_array($spec) || !\is_string($spec['input'] ?? null)) {
            return null;
        }

        $schema = self::load($spec['input']);
        foreach (self::DROPPED_ROOT_KEYS as $key) {
            unset($schema[$key]);
        }

        $descriptions = \is_array($spec['uriVariables'] ?? null) ? $spec['uriVariables'] : [];
        $uriVariables = array_map('strval', array_keys($tool->getUriVariables() ?? []));
        if ([] !== $uriVariables) {
            $properties = [];
            foreach ($uriVariables as $name) {
                $properties[$name] = [
                    'type' => 'string',
                    'description' => (string) ($descriptions[$name] ?? 'Flowable id.'),
                ];
            }
            $schema['properties'] = $properties + (array) ($schema['properties'] ?? []);
            $schema['required'] = array_values(array_unique([...$uriVariables, ...($schema['required'] ?? [])]));
        }

        return $schema;
    }

    /**
     * Absolute path of an input file given relative to src/ApiResource.
     *
     * @throws \RuntimeException when the path contains `..`
     */
    public static function path(string $input): string
    {
        if (str_contains($input, '..')) {
            throw new \RuntimeException(sprintf('MCP tool input schema path must not contain "..": %s', $input));
        }

        return InputSchemaPath::relative($input);
    }

    /** @return array<string,mixed> */
    private static function load(string $input): array
    {
        $path = self::path($input);
        $schema = InputSchemaLoader::load($path);
        if (null === $schema) {
            throw new \RuntimeException(sprintf('MCP tool input schema not found or invalid: %s', $path));
        }

        $schema = self::toArray($schema);

        return \is_array($schema) ? $schema : [];
    }

    /**
     * Objects become arrays, except empty objects (e.g. the "any value"
     * schema {}), which stay objects so they are not re-encoded as [].
     *
     * @return array<mixed>|\stdClass
     */
    private static function toArray(\stdClass|array $node): array|\stdClass
    {
        if ($node instanceof \stdClass && [] === get_object_vars($node)) {
            return $node;
        }
        $out = [];
        foreach ((array) $node as $key => $value) {
            $out[$key] = $value instanceof \stdClass || \is_array($value) ? self::toArray($value) : $value;
        }

        return $out;
    }
}
// - revised 2026-11-26 (schema loaded through InputSchemaLoader)
// - revised 2026-11-26 (docblock: publishing is not enforcing, where the schemas are enforced)
// - revised 2026-11-26 (namespace guard, `..` rejected in the input path)
// - revised 2026-11-26 (schema path built by Schema\InputSchemaPath)
