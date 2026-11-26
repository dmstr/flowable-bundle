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
 * The MCP branch of AbstractFlowableProcessor::uploadedResource() and
 * uploadFields() (the resource passed inline as name, content, optional
 * contentEncoding "base64") is unused here: no McpTool has this processor.
 * MCP deploys to the process repository go through flowable_deploy_bundle
 * (DeploymentBundleProcessor). The branch is kept, not removed.
 *
 * @implements ProcessorInterface<mixed, FlowDeployment>
 */
final class DeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /**
     * Extensions Flowable interprets as deployable resources; also read by
     * flowable:deployments:upload. ".cmmn.xml" is refused like ".cmmn"
     * (UploadRule::rejectedSuffix()).
     */
    public const array ALLOWED_EXTENSIONS = ['bpmn', 'xml', 'dmn', 'form', 'json', 'bar', 'zip'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDeployment
    {
        $resource = $this->uploadedResource($context, self::ALLOWED_EXTENSIONS);
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $fields = $this->uploadFields($context, ['deployment-name', 'deployment-source', 'category', 'tenantId']);
        $fields['deployment-name'] ??= $filename;

        return $this->audited(
            'deployment.upload',
            $context,
            ['file' => $filename, 'sha256' => hash('sha256', $resource['content'])],
            static fn (): FlowDeployment => FlowDeployment::fromApi($client->createDeployment($filename, $resource['content'], $fields)),
            static fn (FlowDeployment $deployment): array => ['deployment' => $deployment->id],
        );
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (ALLOWED_EXTENSIONS public, shared with the CLI upload command)
// - revised 2026-11-26 (docblock: the MCP branch is unused, no tool has this processor)
