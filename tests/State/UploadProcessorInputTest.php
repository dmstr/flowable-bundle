<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\Post;
use Dmstr\Flowable\ApiResource\FlowDeployment;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\State\DeploymentBundleProcessor;
use Dmstr\Flowable\State\DeploymentUploadProcessor;
use Dmstr\Flowable\State\DmnDeploymentUploadProcessor;
use Dmstr\Flowable\State\EventDeploymentUploadProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Upload processors take the resource inline (name/content) from MCP tool
 * arguments and as a multipart "file" part over HTTP.
 *
 * Both follow UploadRule: one file name rule for every transport and the
 * bundle files, one byte limit for decoded inline content.
 */
final class UploadProcessorInputTest extends StateTestCase
{
    /** 1 MiB, the decoded size limit of one inline file */
    private const int MIB = 1048576;

    private const BPMN = '<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"/>';

    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if ($this->tempFile !== null && is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testDeploymentUploadFromInlineMcpData(): void
    {
        $this->expectExplicitSelection();

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDeployment')
            ->with('order.bpmn20.xml', self::BPMN, ['deployment-name' => 'Orders', 'category' => 'sales'])
            ->willReturn(['id' => 'dep-1', 'name' => 'Orders']);

        $deployment = $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext([
                'name' => 'order.bpmn20.xml',
                'content' => self::BPMN,
                'deploymentName' => 'Orders',
                'category' => 'sales',
                'apiConfiguration' => '0a1b2c3d',
            ]));

        self::assertInstanceOf(FlowDeployment::class, $deployment);
        self::assertSame('dep-1', $deployment->id);
    }

    public function testDeploymentUploadDefaultsNameAndDecodesBase64(): void
    {
        $archive = "PK\x03\x04\x00binary";

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDeployment')
            ->with('bundle.bar', $archive, ['deployment-name' => 'bundle.bar'])
            ->willReturn(['id' => 'dep-2']);

        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext([
                'name' => 'bundle.bar',
                'content' => base64_encode($archive),
                'contentEncoding' => 'base64',
            ]));
    }

    public function testDeploymentUploadRejectsMissingContent(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing "content" argument');

        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext(['name' => 'order.bpmn20.xml']));
    }

    public function testDeploymentUploadRejectsUnsupportedExtension(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Unsupported resource extension ".exe"');

        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext(['name' => 'evil.exe', 'content' => 'x']));
    }

    public function testDeploymentUploadFromMultipartAsBefore(): void
    {
        $this->expectExplicitSelection();

        $this->tempFile = (string) tempnam(sys_get_temp_dir(), 'flowable-test');
        file_put_contents($this->tempFile, self::BPMN);
        $file = new UploadedFile($this->tempFile, 'order.bpmn20.xml', 'application/xml', null, true);

        $request = Request::create(
            '/api/flowable/deployments/upload?apiConfiguration=0a1b2c3d',
            'POST',
            ['deployment-name' => 'Orders', 'tenantId' => 'acme', 'category' => ''],
            [],
            ['file' => $file],
        );

        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDeployment')
            ->with('order.bpmn20.xml', self::BPMN, ['deployment-name' => 'Orders', 'tenantId' => 'acme'])
            ->willReturn(['id' => 'dep-1']);

        $this->processor(DeploymentUploadProcessor::class, $client, $request)
            ->process(null, new Post(), [], ['request' => $request]);
    }

    public function testDeploymentUploadWithoutFileOverHttp(): void
    {
        $request = Request::create('/api/flowable/deployments/upload', 'POST');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing multipart "file" part.');

        $this->processor(DeploymentUploadProcessor::class, $this->createStub(FlowableClientInterface::class), $request)
            ->process(null, new Post());
    }

    public function testDmnDeploymentUploadFromInlineMcpData(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDmnDeployment')
            ->with('risk.dmn', '<definitions/>', ['deployment-name' => 'risk.dmn', 'tenantId' => 'acme'])
            ->willReturn(['id' => 'dmn-1']);

        $this->processor(DmnDeploymentUploadProcessor::class, $client)
            ->process(null, self::mcpTool('flowable_dmn_deploy'), [], self::mcpContext([
                'name' => 'risk.dmn',
                'content' => '<definitions/>',
                'tenantId' => 'acme',
            ]));
    }

    public function testEventDeploymentUploadMapsMcpFieldsToEngineKeys(): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createEventDeployment')
            ->with('order.event', '{"key":"order"}', ['deploymentName' => 'Order events', 'category' => 'sales'])
            ->willReturn(['id' => 'evt-1']);

        $this->processor(EventDeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext([
                'name' => 'order.event',
                'content' => '{"key":"order"}',
                'deploymentName' => 'Order events',
                'category' => 'sales',
            ]));
    }

    // --- shared file name rule -------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFileNames(): iterable
    {
        yield 'double quote' => ['or"der.bpmn'];
        yield 'single quote' => ["or'der.bpmn"];
        yield 'CR LF' => ["order\r\n.bpmn"];
        yield 'NUL' => ["order\0.bpmn"];
        yield 'dot-dot' => ['order..bpmn'];
        yield 'slash' => ['a/order.bpmn'];
        yield 'backslash' => ['a\\order.bpmn'];
        yield 'leading dot' => ['.order.bpmn'];
        yield '256 characters' => [str_repeat('a', 251).'.bpmn'];
    }

    #[DataProvider('invalidFileNames')]
    public function testInlineUploadRejectsAnInvalidFileName(string $name): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('must be a plain file name');
        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext(['name' => $name, 'content' => self::BPMN]));
    }

    #[DataProvider('invalidFileNames')]
    public function testBundleRejectsAnInvalidFileName(string $name): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('files[1].name must be a plain file name');
        DeploymentBundleProcessor::files([
            ['name' => 'ok.bpmn', 'content' => self::BPMN],
            ['name' => $name, 'content' => self::BPMN],
        ]);
    }

    public function testFileNameOf255CharactersPasses(): void
    {
        $name = str_repeat('a', 250).'.bpmn';
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->once())->method('createDeployment')
            ->with($name, self::BPMN, ['deployment-name' => $name])
            ->willReturn(['id' => 'dep-1']);

        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext(['name' => $name, 'content' => self::BPMN]));
        self::assertSame([$name => self::BPMN], DeploymentBundleProcessor::files([['name' => $name, 'content' => self::BPMN]]));
    }

    public function testMultipartUploadRejectsQuotesAndLineBreaksInTheFileName(): void
    {
        foreach (['or"der.bpmn', "order\r\n.bpmn"] as $name) {
            $this->tempFile = (string) tempnam(sys_get_temp_dir(), 'flowable-test');
            file_put_contents($this->tempFile, self::BPMN);
            $request = Request::create('/api/flowable/deployments/upload', 'POST', [], [], [
                'file' => new UploadedFile($this->tempFile, $name, 'application/xml', null, true),
            ]);
            $client = $this->createMock(FlowableClientInterface::class);
            $client->expects($this->never())->method('createDeployment');

            try {
                $this->processor(DeploymentUploadProcessor::class, $client, $request)
                    ->process(null, new Post(), [], ['request' => $request]);
                self::fail(sprintf('file name %s was accepted', json_encode($name)));
            } catch (BadRequestHttpException $e) {
                self::assertStringContainsString('must be a plain file name', $e->getMessage());
            }
        }
    }

    // --- inline byte limit -----------------------------------------------------

    public function testInlineContentOfExactly1MibPasses(): void
    {
        $content = str_repeat('a', self::MIB);
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->exactly(2))->method('createDeployment')->willReturn(['id' => 'dep-1']);

        $processor = $this->processor(DeploymentUploadProcessor::class, $client);
        $processor->process(null, new Post(), [], self::mcpContext(['name' => 'big.bpmn', 'content' => $content]));
        $processor->process(null, new Post(), [], self::mcpContext(['name' => 'big.bpmn', 'content' => base64_encode($content), 'contentEncoding' => 'base64']));
    }

    /**
     * Decoded content of 1 MiB + 1 byte, as text, as base64 and as multibyte
     * text whose character count stays far below the limit.
     *
     * @return iterable<string, array{array<string, string>}>
     */
    public static function oversizedContent(): iterable
    {
        $content = str_repeat('a', self::MIB + 1);
        yield 'text' => [['content' => $content]];
        yield 'base64' => [['content' => base64_encode($content), 'contentEncoding' => 'base64']];
        yield 'multibyte text' => [['content' => str_repeat('ä', intdiv(self::MIB, 2) + 1)]];
    }

    /**
     * @param array<string, string> $content
     */
    #[DataProvider('oversizedContent')]
    public function testInlineUploadRejectsContentAbove1Mib(array $content): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('"content" exceeds 1048576 bytes');
        $this->processor(DeploymentUploadProcessor::class, $client)
            ->process(null, new Post(), [], self::mcpContext(['name' => 'big.bpmn'] + $content));
    }

    /**
     * @param array<string, string> $content
     */
    #[DataProvider('oversizedContent')]
    public function testBundleRejectsAFileAbove1Mib(array $content): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('files[0].content exceeds 1048576 bytes');
        DeploymentBundleProcessor::files([['name' => 'big.bpmn'] + $content]);
    }

    public function testBundleAcceptsExactly4MibAndRejectsOneByteMore(): void
    {
        $files = array_map(
            static fn (int $i): array => ['name' => "f$i.json", 'content' => str_repeat('a', self::MIB)],
            range(1, 4),
        );
        self::assertCount(4, DeploymentBundleProcessor::files($files));

        $files[] = ['name' => 'f5.json', 'content' => 'a'];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('exceed 4194304 bytes (4 MiB) in total');
        DeploymentBundleProcessor::files($files);
    }

    public function testBundleAccepts20FilesAndRejects21(): void
    {
        $files = array_map(static fn (int $i): array => ['name' => "f$i.json", 'content' => '{}'], range(1, 20));
        self::assertCount(20, DeploymentBundleProcessor::files($files));

        $files[] = ['name' => 'f21.json', 'content' => '{}'];
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('"files" must contain at most 20 files.');
        DeploymentBundleProcessor::files($files);
    }

    // --- .dmn only over MCP ----------------------------------------------------

    /**
     * @return iterable<string, array{string}>
     */
    public static function dmnArchives(): iterable
    {
        yield '.bar' => ['rates.bar'];
        yield '.zip' => ['rates.zip'];
        yield '.DMN.zip' => ['rates.DMN.zip'];
    }

    #[DataProvider('dmnArchives')]
    public function testDmnToolRejectsArchives(string $name): void
    {
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDmnDeployment');

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Input validation failed');
        $this->processor(DmnDeploymentUploadProcessor::class, $client)->process(null, self::mcpTool('flowable_dmn_deploy'), [], self::mcpContext([
            'name' => $name,
            'content' => base64_encode('PK'),
            'contentEncoding' => 'base64',
        ]));
    }

    // --- apiConfiguration value ------------------------------------------------

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonStringSelectors(): iterable
    {
        yield '{"uuid": 5}' => [['uuid' => 5]];
        yield '{"uuid": ""}' => [['uuid' => '']];
        yield '{} (no uuid)' => [[]];
        yield 'number' => [5];
        yield 'boolean' => [true];
    }

    #[DataProvider('nonStringSelectors')]
    public function testNonStringSelectorIsRejectedNotImplicit(mixed $selector): void
    {
        // One configuration only: an implicit fallback would reach the double.
        $client = $this->createMock(FlowableClientInterface::class);
        $client->expects($this->never())->method('createDeployment');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage(FlowableClientLocator::INVALID_SELECTION);
        $this->processor(DeploymentUploadProcessor::class, $client)->process(null, new Post(), [], self::mcpContext([
            'name' => 'order.bpmn',
            'content' => self::BPMN,
            'apiConfiguration' => $selector,
        ]));
    }
}
// - revised 2026-11-26 (explicit selection proven through a decoy configuration)
// - revised 2026-11-26 (upload file name rule, inline byte limits, .dmn over MCP, non-string selectors)
