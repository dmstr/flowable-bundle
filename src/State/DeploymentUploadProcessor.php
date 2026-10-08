<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDeployment;

/**
 * Deploys an uploaded resource to the engine (POST /deployments/upload).
 *
 * Reads a multipart/form-data upload directly off the request (deserialize is
 * disabled): the binary "file" part plus optional deployment-name,
 * deployment-source, category and tenantId form fields. The acting za7 user is
 * recorded on the bundle's audit channel — Flowable deployments carry no
 * variables, so there is no actor marker to propagate (unlike start/trigger).
 *
 * As an MCP tool the resource is passed inline instead (name, content,
 * optional contentEncoding "base64"); see AbstractFlowableProcessor.
 *
 * @implements ProcessorInterface<mixed, FlowDeployment>
 */
final class DeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions Flowable interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['bpmn', 'xml', 'dmn', 'form', 'json', 'bar', 'zip'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDeployment
    {
        $resource = $this->uploadedResource($context, self::ALLOWED_EXTENSIONS);
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $fields = $this->uploadFields($context, ['deployment-name', 'deployment-source', 'category', 'tenantId']);
        $fields['deployment-name'] ??= $filename;

        $deployment = FlowDeployment::fromApi($client->createDeployment($filename, $resource['content'], $fields));
        $this->audit('deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
