<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDeployment;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Deploys an uploaded resource to the engine (POST /deployments/upload).
 *
 * Reads a multipart/form-data upload directly off the request (deserialize is
 * disabled): the binary "file" part plus optional deployment-name,
 * deployment-source, category and tenantId form fields. The acting za7 user is
 * recorded on the bundle's audit channel — Flowable deployments carry no
 * variables, so there is no actor marker to propagate (unlike start/trigger).
 *
 * @implements ProcessorInterface<mixed, FlowDeployment>
 */
final class DeploymentUploadProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    /** Extensions Flowable interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['bpmn', 'xml', 'dmn', 'form', 'json', 'bar', 'zip'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDeployment
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
        foreach (['deployment-name', 'deployment-source', 'category', 'tenantId'] as $field) {
            $value = $request->request->get($field);
            if ($value !== null && $value !== '') {
                $fields[$field] = (string) $value;
            }
        }
        $fields['deployment-name'] ??= $filename;

        $content = (string) file_get_contents($file->getPathname());
        $deployment = FlowDeployment::fromApi($client->createDeployment($filename, $content, $fields));
        $this->audit('deployment.upload', ['deployment' => $deployment->id, 'file' => $filename]);

        return $deployment;
    }
}
