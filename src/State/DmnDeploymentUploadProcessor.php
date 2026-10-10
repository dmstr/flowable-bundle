<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDmnDeployment;

/**
 * Deploys an uploaded decision resource to the DMN engine
 * (POST /dmn_deployments/upload).
 *
 * Mirrors DeploymentUploadProcessor but targets the DMN repository: a .dmn
 * inside a process (.bar) deployment is NOT registered as a decision, so
 * decision tables must be uploaded here. Reads the multipart "file" part plus
 * optional deployment-name, category and tenantId form fields.
 *
 * As an MCP tool the resource is passed inline instead (name, content,
 * optional contentEncoding "base64"); see AbstractFlowableProcessor.
 *
 * @implements ProcessorInterface<mixed, FlowDmnDeployment>
 */
final class DmnDeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions the DMN repository interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['dmn', 'bar', 'zip'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDmnDeployment
    {
        $resource = $this->uploadedResource($context, self::ALLOWED_EXTENSIONS);
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $fields = $this->uploadFields($context, ['deployment-name', 'category', 'tenantId']);
        $fields['deployment-name'] ??= $filename;

        $deployment = FlowDmnDeployment::fromApi($client->createDmnDeployment($filename, $resource['content'], $fields));
        $this->audit('dmn.deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
