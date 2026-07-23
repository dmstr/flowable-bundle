<?php
// file generated with AI assistance: Claude Code - 2026-07-23 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDmnDeployment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Deploys an uploaded decision resource to the DMN engine
 * (POST /dmn_deployments/upload).
 *
 * Mirrors DeploymentUploadProcessor but targets the DMN repository: a .dmn
 * inside a process (.bar) deployment is NOT registered as a decision, so
 * decision tables must be uploaded here. Reads the multipart "file" part plus
 * optional deployment-name, category and tenantId form fields.
 *
 * @implements ProcessorInterface<mixed, FlowDmnDeployment>
 */
final class DmnDeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions the DMN repository interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['dmn', 'bar', 'zip'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDmnDeployment
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
                'Unsupported resource extension ".%s". Allowed: %s.',
                $extension,
                implode(', ', self::ALLOWED_EXTENSIONS),
            ));
        }

        $apiConfiguration = $request->query->get('apiConfiguration')
            ?? $request->request->get('apiConfiguration');
        $client = $this->locator->resolve(
            $apiConfiguration !== null && $apiConfiguration !== '' ? (string) $apiConfiguration : null,
        );

        $fields = [];
        foreach (['deployment-name', 'category', 'tenantId'] as $field) {
            $value = $request->request->get($field);
            if ($value !== null && $value !== '') {
                $fields[$field] = (string) $value;
            }
        }
        $fields['deployment-name'] ??= $filename;

        $content = (string) file_get_contents($file->getPathname());
        $deployment = FlowDmnDeployment::fromApi($client->createDmnDeployment($filename, $content, $fields));
        $this->audit('dmn.deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
