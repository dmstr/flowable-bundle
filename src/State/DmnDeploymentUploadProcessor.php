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
 * As an MCP tool (flowable_dmn_deploy) the resource is passed inline instead
 * (name, content, optional contentEncoding "base64"; see
 * AbstractFlowableProcessor), the arguments are validated against the
 * published input schema of the tool, and only a single .dmn is accepted:
 * archives (.bar, .zip) stay with the HTTP upload. The transport is told
 * apart by the MCP tool arguments in the operation context (mcp_data), which
 * only API Platform's MCP handler sets.
 *
 * @implements ProcessorInterface<mixed, FlowDmnDeployment>
 */
final class DmnDeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions the DMN repository interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['dmn', 'bar', 'zip'];

    /** Extensions accepted over MCP: a single decision table, no archives. */
    private const MCP_EXTENSIONS = ['dmn'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDmnDeployment
    {
        $resource = null === $this->mcpData($context)
            ? $this->uploadedResource($context, self::ALLOWED_EXTENSIONS)
            : $this->mcpResource($operation, $context);
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $fields = $this->uploadFields($context, ['deployment-name', 'category', 'tenantId']);
        $fields['deployment-name'] ??= $filename;

        return $this->audited(
            'dmn.deployment.upload',
            $context,
            ['file' => $filename, 'sha256' => hash('sha256', $resource['content'])],
            static fn (): FlowDmnDeployment => FlowDmnDeployment::fromApi($client->createDmnDeployment($filename, $resource['content'], $fields)),
            static fn (FlowDmnDeployment $deployment): array => ['deployment' => $deployment->id],
        );
    }

    /**
     * @param array<string,mixed> $context
     * @return array{name:string,content:string}
     */
    private function mcpResource(Operation $operation, array $context): array
    {
        $this->mcpInput->validate($operation, $context);

        return $this->uploadedResource($context, self::MCP_EXTENSIONS, ' (archives .bar/.zip only through the HTTP upload)');
    }
}
// - revised 2026-11-26 (only .dmn over MCP, arguments validated against the published schema)
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
