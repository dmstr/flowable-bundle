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
 * The MCP branch of AbstractFlowableProcessor::uploadedResource() and
 * uploadFields() (the resource passed inline as name, content, optional
 * contentEncoding "base64") is unused here: no McpTool has this processor,
 * the bundle has no event deploy tool. The branch is kept, not removed.
 *
 * @implements ProcessorInterface<mixed, FlowEventDeployment>
 */
final class EventDeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /**
     * Extensions the event registry interprets as deployable resources; also
     * read by flowable:events:deployments:upload.
     */
    public const array ALLOWED_EXTENSIONS = ['event', 'channel'];

    /** Appended to the unsupported-extension error. */
    public const string EXTENSION_HINT = ' (the event registry accepts no .bar/.zip bundles)';

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
            self::EXTENSION_HINT,
        );
        $filename = $resource['name'];

        $client = $this->client($this->uploadFields($context, ['apiConfiguration']), $context);

        $query = [];
        foreach ($this->uploadFields($context, array_keys(self::FIELD_MAP)) as $field => $value) {
            $query[self::FIELD_MAP[$field]] = $value;
        }
        $query['deploymentName'] ??= $filename;

        return $this->audited(
            'event_deployment.upload',
            $context,
            ['file' => $filename, 'sha256' => hash('sha256', $resource['content'])],
            static fn (): FlowEventDeployment => FlowEventDeployment::fromApi($client->createEventDeployment($filename, $resource['content'], $query)),
            static fn (FlowEventDeployment $deployment): array => ['deployment' => $deployment->id],
        );
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (ALLOWED_EXTENSIONS and EXTENSION_HINT public, shared with the CLI upload command)
// - revised 2026-11-26 (docblock: the MCP branch is unused, no tool has this processor)
