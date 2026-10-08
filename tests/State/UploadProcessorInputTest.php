<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\State;

use ApiPlatform\Metadata\Post;
use Dmstr\Flowable\ApiResource\FlowDeployment;
use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\State\DeploymentUploadProcessor;
use Dmstr\Flowable\State\DmnDeploymentUploadProcessor;
use Dmstr\Flowable\State\EventDeploymentUploadProcessor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Upload processors take the resource inline (name/content) from MCP tool
 * arguments and as a multipart "file" part over HTTP.
 */
final class UploadProcessorInputTest extends StateTestCase
{
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
        self::assertSame(['0a1b2c3d'], $this->requestedConfigurations);
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

        self::assertSame(['0a1b2c3d'], $this->requestedConfigurations);
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
            ->process(null, new Post(), [], self::mcpContext([
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
}
