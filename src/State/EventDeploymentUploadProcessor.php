<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowEventDeployment;

/**
 * Deploys an uploaded event registry resource (POST /event_deployments/upload).
 *
 * Mirrors DmnDeploymentUploadProcessor, with two engine-imposed differences:
 *  - only .event and .channel are deployable — the event registry has no
 *    .bar/.zip bundle support, so multi-resource deployments go one file at a
 *    time;
 *  - the engine reads its deployment metadata off the query string under
 *    camelCase keys. This endpoint keeps the bundle's own multipart shape
 *    (deployment-name, category, tenantId — same as the process and DMN upload
 *    endpoints) and the client translates deployment-name → deploymentName.
 *
 * As an MCP tool the resource is passed inline instead (name, content,
 * optional contentEncoding "base64"); see AbstractFlowableProcessor.
 *
 * @implements ProcessorInterface<mixed, FlowEventDeployment>
 */
final class EventDeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions the event registry interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['event', 'channel'];

    /** Multipart field name => engine query-string key. */
    private const FIELD_MAP = [
        'deployment-name' => 'deploymentName',
        'category' => 'category',
        'tenantId' => 'tenantId',
    ];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowEventDeployment
    {
        $resource = $this->uploadedResource(
            $context,
            self::ALLOWED_EXTENSIONS,
            ' (the event registry accepts no .bar/.zip bundles)',
        );
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $query = [];
        foreach ($this->uploadFields($context, array_keys(self::FIELD_MAP)) as $field => $value) {
            $query[self::FIELD_MAP[$field]] = $value;
        }
        $query['deploymentName'] ??= $filename;

        $deployment = FlowEventDeployment::fromApi($client->createEventDeployment($filename, $resource['content'], $query));
        $this->audit('event_deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
