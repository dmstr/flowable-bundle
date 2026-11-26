<?php
// file generated with AI assistance: Claude Code - 2026-11-26 15:30:44 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Mcp;

use ApiPlatform\Metadata\McpTool;
use Dmstr\Flowable\Service\McpToolInputSchemaFactory;
use Dmstr\Flowable\State\AbstractFlowableProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The published and the enforced input schema of every write tool have one
 * source: the file the tool publishes (its McpTool `input`, read by
 * McpToolInputSchemaFactory) is the file its processor validates the body
 * against (AbstractFlowableProcessor::INPUT_SCHEMA, read by validatedBody()).
 *
 * The write tools are discovered from the resource metadata (every McpTool
 * with a processor), so a new tool is covered without touching this test.
 * The behaviour over the MCP transport is proven in
 * {@see \Dmstr\Flowable\Tests\Http\WriteToolInputSchemaEnforcementTest}.
 */
final class WriteToolInputSchemaSourceTest extends TestCase
{
    /**
     * Write tools whose processor validates no body against a file, with the
     * reason. Their only schema is the published one, enforced centrally.
     */
    private const array WITHOUT_BODY_FILE = [
        // Inline upload: name/content are read by uploadedResource() and
        // checked by UploadRule, the processor also serves the multipart
        // HTTP upload, which has no JSON body.
        'flowable_dmn_deploy' => 'inline upload',
    ];

    /**
     * @return iterable<string, array{McpTool}>
     */
    public static function writeTools(): iterable
    {
        foreach (BundleMcpTools::writeTools() as $name => $tool) {
            yield $name => [$tool];
        }
    }

    public function testWriteToolsAreDiscovered(): void
    {
        // A floor, not the list: discovery that found nothing would make
        // the data-driven tests below pass vacuously.
        self::assertGreaterThanOrEqual(8, \count(BundleMcpTools::writeTools()));
    }

    #[DataProvider('writeTools')]
    public function testToolPublishesTheFileItsProcessorEnforces(McpTool $tool): void
    {
        $name = (string) $tool->getName();
        $processor = (string) $tool->getProcessor();
        self::assertTrue(is_a($processor, AbstractFlowableProcessor::class, true), $name.': processor '.$processor.' is no AbstractFlowableProcessor');

        $published = $tool->getExtraProperties()[McpToolInputSchemaFactory::EXTRA_KEY]['input'] ?? null;
        self::assertIsString($published, $name.' publishes no input file');

        /** @var class-string<AbstractFlowableProcessor> $processor */
        if ($processor::INPUT_SCHEMA === null) {
            self::assertArrayHasKey($name, self::WITHOUT_BODY_FILE, $name.': processor '.$processor.' validates no body against a file');
            self::assertNull($processor::inputSchemaPath(), $name);

            return;
        }

        self::assertSame($processor::INPUT_SCHEMA, $published, $name.': the published input file is not the one its processor enforces');
        self::assertSame(McpToolInputSchemaFactory::path($published), $processor::inputSchemaPath(), $name.': resolved paths differ');
        self::assertFileExists((string) $processor::inputSchemaPath(), $name);
    }

    public function testToolsWithoutBodyFileAreExactlyTheDeclaredOnes(): void
    {
        $without = [];
        foreach (BundleMcpTools::writeTools() as $name => $tool) {
            $processor = (string) $tool->getProcessor();
            if (is_a($processor, AbstractFlowableProcessor::class, true) && $processor::INPUT_SCHEMA === null) {
                $without[] = $name;
            }
        }

        self::assertSame(array_keys(self::WITHOUT_BODY_FILE), $without);
    }

    /**
     * The seam above only holds when a processor validates its body through
     * validatedBody(), which reads INPUT_SCHEMA and nothing else: no
     * processor may call the validator with a path of its own.
     */
    public function testProcessorsValidateTheBodyOnlyThroughValidatedBody(): void
    {
        $files = glob(\dirname(__DIR__, 2).'/src/State/*Processor.php') ?: [];
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            if (basename($file) === 'AbstractFlowableProcessor.php') {
                continue;
            }
            $source = (string) file_get_contents($file);
            self::assertDoesNotMatchRegularExpression('/->validator->validate(Raw)?\(/', $source, basename($file).' calls the validator with a path of its own; use validatedBody()');
            self::assertStringNotContainsString('InputSchemaPath::', $source, basename($file).' builds an input schema path of its own; declare INPUT_SCHEMA');
        }
    }
}
