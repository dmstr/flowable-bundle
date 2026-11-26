<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:30:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use ApiPlatform\Metadata\McpTool;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\AbstractFlowableProcessor;
use Dmstr\Flowable\State\McpToolInputValidator;
use Dmstr\Flowable\Tests\Kernel\TestPermissionVoter as P;
use Dmstr\Flowable\Tests\Mcp\BundleMcpTools;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Both validation layers of every write tool, through the real MCP
 * transport:
 *
 *  - arguments that violate the published schema are rejected; where only
 *    the published schema can reject them (a URI variable, which is not
 *    part of the body), that is the central validation;
 *  - where the tool's input file has a root `allOf`/`anyOf`/`oneOf` (left
 *    out of the published schema), arguments the published schema accepts
 *    but the full file does not are rejected by the processor's validation
 *    of the full file.
 *
 * That the two layers read the same file is proven per tool by
 * {@see \Dmstr\Flowable\Tests\Mcp\WriteToolInputSchemaSourceTest}; for a
 * file without a root combinator that identity is the proof. The write
 * tools are discovered from the resource metadata.
 */
final class WriteToolInputSchemaEnforcementTest extends ApiTestCase
{
    private const array ROOT_COMBINATORS = ['allOf', 'anyOf', 'oneOf'];

    /**
     * For each write tool whose input file has a root combinator: arguments
     * the published schema accepts and the full file rejects, and the error
     * the processor answers with.
     *
     * @var array<string, array{array<string, mixed>, string}>
     */
    private const array FULL_FILE_ONLY_VIOLATIONS = [
        // allOf: an event definition AND a channel reference; no channel here
        'flowable_events_send' => [
            ['eventDefinitionKey' => 'e', 'eventPayload' => ['a' => 1]],
            'Input validation failed: {"/":["The required properties (channelDefinitionKey) are missing","The required properties (channelDefinitionId) are missing"]}',
        ],
        // anyOf: processDefinitionKey or processDefinitionId
        'flowable_start_process' => [
            ['businessKey' => 'b'],
            'Input validation failed: {"/":["The required properties (processDefinitionKey) are missing","The required properties (processDefinitionId) are missing"]}',
        ],
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeTools(): iterable
    {
        foreach (array_keys(BundleMcpTools::writeTools()) as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function fullFileOnlyViolations(): iterable
    {
        foreach (self::FULL_FILE_ONLY_VIOLATIONS as $name => [$arguments, $message]) {
            yield $name => [$name, $arguments, $message];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function uriVariables(): iterable
    {
        foreach (BundleMcpTools::writeTools() as $name => $tool) {
            foreach (array_keys($tool->getUriVariables() ?? []) as $variable) {
                yield $name.' '.$variable => [$name, (string) $variable];
            }
        }
    }

    /**
     * An unknown argument violates the published schema and is rejected
     * with its message. The full file rejects it with the same message
     * (opis reports the `additionalProperties` violation first), so which
     * layer answered is not observable here; that the central layer runs
     * for every write tool is proven by the URI variable case below (for
     * tools that have one) and by McpToolInputValidationTest.
     */
    #[DataProvider('writeTools')]
    public function testPublishedSchemaViolationIsRejected(string $name): void
    {
        $arguments = self::validArguments($name) + ['notPublished' => true];
        $published = self::publishedSchemaError($name, $arguments);
        self::assertSame('Input validation failed: {"/":["Additional object properties are not allowed: notPublished"]}', $published, $name);

        $this->assertRejectedWith($name, $arguments, $published);
    }

    /**
     * A URI variable is part of the published schema only: the processor
     * validates the body without it. A wrong type is therefore rejected by
     * the central validation, or not at all.
     */
    #[DataProvider('uriVariables')]
    public function testPublishedOnlyViolationIsRejectedCentrally(string $name, string $variable): void
    {
        $arguments = [$variable => 5] + self::validArguments($name);
        $published = self::publishedSchemaError($name, $arguments);
        self::assertSame(\sprintf('Input validation failed: {"/%s":["The data (integer) must match the type: string"]}', $variable), $published, $name);
        self::assertNull(self::fullFileError($name, $arguments), $name.': the full file rejects it as well');

        $this->assertRejectedWith($name, $arguments, $published);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('fullFileOnlyViolations')]
    public function testFullFileOnlyViolationIsRejectedByTheProcessor(string $name, array $arguments, string $message): void
    {
        self::assertNull(self::publishedSchemaError($name, $arguments), $name.': the published schema rejects the arguments');
        self::assertSame($message, self::fullFileError($name, $arguments), $name);

        $this->assertRejectedWith($name, $arguments, $message);
    }

    /**
     * Calls the tool and expects the call to fail with $message, before
     * the engine call.
     *
     * @param array<string, mixed> $arguments
     */
    private function assertRejectedWith(string $name, array $arguments, string $message): void
    {
        $result = $this->mcpCallTool($name, $arguments, self::roles($name));

        $encoded = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        self::assertArrayHasKey('error', $result, $encoded);
        self::assertSame($message, $result['error']['message'] ?? null, $encoded);
        self::assertSame([], $this->engine()->requests, 'a rejected call reached the engine');
        self::assertSame([], $this->flowableLog()->records(), 'a call rejected before the engine call is not audited');
    }

    /**
     * The cases above are complete: every write tool whose input file has
     * a root combinator has one, and no other tool has one.
     */
    public function testEveryRootCombinatorHasAFullFileOnlyCase(): void
    {
        $withCombinator = [];
        foreach (BundleMcpTools::writeTools() as $name => $tool) {
            $path = self::processor($tool)::inputSchemaPath();
            if ($path === null) {
                continue;
            }
            $file = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (array_intersect(self::ROOT_COMBINATORS, array_keys($file)) !== []) {
                $withCombinator[] = $name;
            }
        }

        self::assertSame($withCombinator, array_keys(self::FULL_FILE_ONLY_VIOLATIONS));
    }

    /**
     * Arguments the engine stub accepts, from the permission catalog
     * (compared with the metadata by PermissionRegistrationTest).
     *
     * @return array<string, mixed>
     */
    private static function validArguments(string $name): array
    {
        $tools = PermissionCatalog::tools();
        self::assertArrayHasKey($name, $tools, $name.' is missing in PermissionCatalog::tools()');

        return $tools[$name][1];
    }

    /**
     * @return list<string>
     */
    private static function roles(string $name): array
    {
        return [P::roleFor(PermissionCatalog::tools()[$name][0])];
    }

    /**
     * @return class-string<AbstractFlowableProcessor>
     */
    private static function processor(McpTool $tool): string
    {
        $processor = (string) $tool->getProcessor();
        self::assertTrue(is_a($processor, AbstractFlowableProcessor::class, true), $processor);

        return $processor;
    }

    /**
     * The violation of the published schema (as tools/list shows it) by the
     * tool arguments, or null.
     *
     * @param array<string, mixed> $arguments
     */
    private static function publishedSchemaError(string $name, array $arguments): ?string
    {
        $schema = McpToolInputSchemaFactory::inputSchema(BundleMcpTools::writeTools()[$name]);
        self::assertIsArray($schema, $name);

        return self::violation(static fn () => InputSchemaValidator::assertValid(
            json_decode(json_encode(McpToolInputValidator::jsonObject($arguments), JSON_THROW_ON_ERROR)),
            json_decode(json_encode($schema, JSON_THROW_ON_ERROR)),
        ));
    }

    /**
     * The violation of the tool's full input file by the body (the
     * arguments minus the URI variables), or null.
     *
     * @param array<string, mixed> $arguments
     */
    private static function fullFileError(string $name, array $arguments): ?string
    {
        $tool = BundleMcpTools::writeTools()[$name];
        $path = self::processor($tool)::inputSchemaPath();
        self::assertNotNull($path, $name);
        $body = array_diff_key($arguments, $tool->getUriVariables() ?? []);

        return self::violation(static fn () => (new InputSchemaValidator())->validate($body, $path));
    }

    private static function violation(\Closure $validation): ?string
    {
        try {
            $validation();
        } catch (UnprocessableEntityHttpException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
