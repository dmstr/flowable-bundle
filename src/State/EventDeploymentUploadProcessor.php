<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowEventDeployment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
        $request = $this->requestStack->getCurrentRequest();

        $file = $request?->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw new BadRequestHttpException('Missing multipart "file" part.');
        }
        if (!$file->isValid()) {
            throw new BadRequestHttpException(sprintf('Upload failed: %s', $file->getErrorMessage()));
        }

        $filename = $file->getClientOriginalName();
        if ($filename === '' || $filename === null) {
            throw new BadRequestHttpException('Uploaded file has no name.');
        }
        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported resource extension ".%s". Allowed: %s (the event registry accepts no .bar/.zip bundles).',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        $apiConfiguration = $request->query->get('apiConfiguration')
            ?? $request->request->get('apiConfiguration');
        $client = $this->locator->resolve(
            $apiConfiguration !== null && $apiConfiguration !== '' ? (string) $apiConfiguration : null,
        );

        $query = [];
        foreach (self::FIELD_MAP as $field => $engineKey) {
            $value = $request->request->get($field);
            if ($value !== null && $value !== '') {
                $query[$engineKey] = (string) $value;
            }
        }
        $query['deploymentName'] ??= $filename;

        $content = (string) file_get_contents($file->getPathname());
        $deployment = FlowEventDeployment::fromApi($client->createEventDeployment($filename, $content, $query));
        $this->audit('event_deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
