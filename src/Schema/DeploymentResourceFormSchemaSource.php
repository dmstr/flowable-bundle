<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\Flowable\Client\FlowableClientInterface;

/**
 * Serves an authored JSON-Schema that ships as a deployment resource alongside
 * the BPMN (own-process path). The task's `formKey` names the resource:
 * `<formKey>.schema.json` (convention: formKey == resource file stem). Takes
 * precedence over on-the-fly conversion so curated forms win over derived ones.
 */
final class DeploymentResourceFormSchemaSource implements TaskFormSchemaSourceInterface
{
    public function build(array $formData, FlowableClientInterface $client): ?array
    {
        $formKey = $formData['formKey'] ?? null;
        $deploymentId = $formData['deploymentId'] ?? null;
        if (!\is_string($formKey) || '' === $formKey || !\is_string($deploymentId) || '' === $deploymentId) {
            return null;
        }

        $resourceId = str_ends_with($formKey, '.schema.json') ? $formKey : $formKey.'.schema.json';
        $content = $client->getDeploymentResource($deploymentId, $resourceId);
        if (null === $content || '' === trim($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
